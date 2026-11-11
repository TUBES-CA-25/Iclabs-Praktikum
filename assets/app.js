function apiUrl(path) { const [route, query] = path.split('?'); return 'api.php?route=' + encodeURIComponent(route) + (query ? '&' + query : '') }
const $ = s => document.querySelector(s), app = $('#app'); let user, authCsrf = '', activePeriod = null, classes = [], page = 'Dashboard', current, tab = 'Absensi', meeting = 1, changes = new Map(), masterData, masterTab = 'mahasiswa';
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
function assistantTierName(person) { const code = person?.kode_tingkat_asisten || person?.tingkat_asisten?.kode, name = person?.nama_tingkat_asisten || person?.tingkat_asisten?.nama; if (!code) return 'Jenjang belum ditetapkan'; return code === 'ASISTEN_3' ? 'Pendamping (Asisten 3)' : (name || code) }
function assistantOptionLabel(person) { return `${person.nama} · ${assistantTierName(person)}` }
const num = v => v === null || v === undefined ? '—' : Number(v).toFixed(1); const roleLabel = { admin: 'Admin Laboratorium', asisten: 'Asisten', dosen: 'Dosen Pengampu', mahasiswa: 'Mahasiswa' };
async function api(path, data, method) { const r = await fetch(apiUrl(path), { method: method || (data ? 'POST' : 'GET'), headers: data ? { 'Content-Type': 'application/json', 'X-CSRF-Token': user?.csrf || authCsrf || '' } : {}, body: data ? JSON.stringify(data) : undefined }); const b = r.status === 204 ? null : await r.json(); if (!r.ok) { if (r.status === 401 && !['/login', '/auth/csrf', '/auth/activation-request', '/auth/password-reset-request'].includes(path)) login(); throw Error(b?.error || 'Permintaan API ditolak.') } return b }
function toast(s) { $('#toast').textContent = s; $('#toast').style.display = 'block'; setTimeout(() => $('#toast').style.display = 'none', 4500) }
function badge(s) { return `<span class="badge ${['Dikunci', 'Terverifikasi', 'Lengkap'].includes(s) ? 'green' : s === 'Perlu Revisi' ? 'red' : ['Diajukan', 'Draft'].includes(s) ? 'yellow' : ''}">${esc(s)}</span>` }
function brand() { return '<div class="brand"><img class="brand-logo" src="assets/logo-iclabs.jpg" alt="Logo ICLabs" width="44" height="47"><div><strong>ICLabs Praktikum</strong><small>FIKOM UMI</small></div></div>' }
function initLoginInteractive() {
  const canvas = document.getElementById("particle-canvas");
  if (canvas) {
    const ctx = canvas.getContext("2d");
    let particlesArray = [];
    const setCanvasSize = () => {
      if (!canvas || !canvas.parentElement) return;
      canvas.width = canvas.parentElement.offsetWidth;
      canvas.height = canvas.parentElement.offsetHeight;
    };
    setCanvasSize();
    window.addEventListener("resize", setCanvasSize);

    class Particle {
      constructor() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 2 + 0.5;
        this.speedX = Math.random() * 0.3 - 0.15;
        this.speedY = Math.random() * 0.3 - 0.15;
        this.color = "rgba(255, 255, 255, 0.4)";
      }
      update() {
        this.x += this.speedX;
        this.y += this.speedY;
        if (this.x > canvas.width || this.x < 0) this.speedX = -this.speedX;
        if (this.y > canvas.height || this.y < 0) this.speedY = -this.speedY;
      }
      draw() {
        ctx.fillStyle = this.color;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    const initP = () => {
      particlesArray = [];
      const numberOfParticles = Math.floor((canvas.height * canvas.width) / 15000);
      for (let i = 0; i < numberOfParticles; i++) particlesArray.push(new Particle());
    };

    let animId;
    const animate = () => {
      if (!document.getElementById("particle-canvas")) {
        cancelAnimationFrame(animId);
        return;
      }
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      for (let i = 0; i < particlesArray.length; i++) {
        particlesArray[i].update();
        particlesArray[i].draw();
        for (let j = i + 1; j < particlesArray.length; j++) {
          const dx = particlesArray[i].x - particlesArray[j].x;
          const dy = particlesArray[i].y - particlesArray[j].y;
          const distance = Math.sqrt(dx * dx + dy * dy);
          if (distance < 100) {
            ctx.beginPath();
            ctx.strokeStyle = `rgba(255, 255, 255, ${0.15 - distance / 800})`;
            ctx.lineWidth = 0.5;
            ctx.moveTo(particlesArray[i].x, particlesArray[i].y);
            ctx.lineTo(particlesArray[j].x, particlesArray[j].y);
            ctx.stroke();
            ctx.closePath();
          }
        }
      }
      animId = requestAnimationFrame(animate);
    };
    initP();
    animate();
  }

  const card = document.getElementById("login-card");
  if (card) {
    const handleMove = (e) => {
      if (!document.getElementById("login-card") || window.innerWidth < 1024) return;
      const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
      const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
      card.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
    };
    const handleEnter = () => {
      if (!document.getElementById("login-card") || window.innerWidth < 1024) return;
      card.style.transition = "none";
    };
    const handleLeave = () => {
      if (!document.getElementById("login-card") || window.innerWidth < 1024) return;
      card.style.transition = "all 0.5s ease";
      card.style.transform = "rotateY(0deg) rotateX(0deg)";
    };
    window.addEventListener("mousemove", handleMove);
    window.addEventListener("mouseenter", handleEnter);
    window.addEventListener("mouseleave", handleLeave);
  }

  const toggleBtn = document.getElementById("toggle-password");
  const pwdInput = document.getElementById("login-password");
  const toggleIcon = document.getElementById("toggle-password-icon");
  if (toggleBtn && pwdInput && toggleIcon) {
    toggleBtn.onclick = () => {
      const show = pwdInput.type === "password";
      pwdInput.type = show ? "text" : "password";
      toggleIcon.className = show ? "fas fa-eye-slash" : "fas fa-eye";
      toggleBtn.setAttribute("aria-label", show ? "Sembunyikan kata sandi" : "Tampilkan kata sandi");
    };
  }

  document.querySelectorAll(".auth-details-box").forEach(box => {
    box.addEventListener("toggle", () => {
      if (box.open) {
        document.querySelectorAll(".auth-details-box").forEach(other => {
          if (other !== box) other.open = false;
        });
      }
    });
  });

  // 5. Theme Mode Management (Dark Mode default)
  const themeToggleBtn = document.getElementById("auth-theme-toggle");
  const loginRoot = document.querySelector(".iclabs-login-split");

  const applyTheme = (theme) => {
    if (!loginRoot) return;
    const isLight = theme === 'light';
    loginRoot.classList.toggle('theme-light', isLight);
    const themeIcon = document.getElementById("auth-theme-icon");
    if (themeIcon) {
      themeIcon.className = isLight ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
    }
    if (themeToggleBtn) {
      const label = isLight ? 'Beralih ke mode gelap' : 'Beralih ke mode terang';
      themeToggleBtn.setAttribute('aria-label', label);
      themeToggleBtn.setAttribute('title', label);
    }
  };

  const savedTheme = localStorage.getItem('iclabs-auth-theme') || 'dark';
  applyTheme(savedTheme);

  if (themeToggleBtn) {
    themeToggleBtn.onclick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      const currentIsLight = loginRoot?.classList.contains('theme-light');
      const nextTheme = currentIsLight ? 'dark' : 'light';
      try { localStorage.setItem('iclabs-auth-theme', nextTheme); } catch { }
      applyTheme(nextTheme);
    };
  }
}

function login() {
  user = null;
  app.innerHTML = `<main class="iclabs-login auth-page iclabs-login-split">
    <!-- Left Showcase Panel (Desktop) -->
    <div class="auth-split-showcase" aria-label="Laboratorium ICLabs">
      <div class="auth-split-bg">
        <img src="assets/gedungfikomm.webp" alt="Gedung FIKOM UMI">
        <div class="overlay-grad-1"></div>
        <div class="overlay-grad-2"></div>
      </div>

      <canvas id="particle-canvas"></canvas>

      <div class="auth-split-content">
        <div class="brand auth-split-logo-badge animate-levitate">
          <img src="assets/navbar-icon.webp" alt="Logo ICLabs" class="brand-logo">
        </div>
        <h1 class="auth-split-title animate-enter">ICLABS-PRAKTIKUM</h1>
        <p class="auth-split-subtitle animate-enter" style="animation-delay: 0.2s">
          Sistem Informasi Praktikum Laboratorium<br>Terintegrasi & Real-time.
        </p>
      </div>

      <div class="auth-split-divider">
        <svg preserveAspectRatio="none" viewBox="0 0 100 100" fill="currentColor">
          <path d="M 100 0 C 60 20 40 60 100 100 Z" fill="#93c5fd" opacity="0.2" transform="translate(-15, 0)"></path>
          <path d="M 100 0 C 50 40 20 80 100 100 Z" fill="#60a5fa" opacity="0.3" transform="translate(-8, 0)"></path>
          <path class="wave-front" d="M 100 0 C 30 30 10 70 100 100 Z" fill="var(--auth-bg-right)"></path>
        </svg>
      </div>
    </div>

    <!-- Right Form Panel -->
    <div class="auth-split-panel tilt-wrapper">
      <div class="bg-grid-slate"></div>
      <div class="glow-sphere glow-sphere-1"></div>
      <div class="glow-sphere glow-sphere-2"></div>

      <div id="login-card" class="tilt-card animate-enter" style="animation-delay: 0.3s">
        <div class="glass-card">
          <!-- Mobile Brand Banner -->
          <div class="auth-mobile-header">
            <img src="assets/navbar-icon.webp" alt="ICLabs Logo">
            <div>
              <strong>IC-LABS PRAKTIKUM</strong>
              <small>Fakultas Ilmu Komputer UMI</small>
            </div>
          </div>

          <div class="auth-card-top-row">
            <div class="auth-panel-heading">
              <h2>Login ICLabs</h2>
              <p>Masuk menggunakan akun pusat ICLabs Anda.</p>
            </div>
            <button type="button" id="auth-theme-toggle" class="auth-theme-pill" aria-label="Ganti mode terang/gelap" title="Ganti mode terang/gelap">
              <i class="fa-solid fa-sun" id="auth-theme-icon"></i>
            </button>
          </div>

          <div id="login-error" role="alert" aria-live="assertive"></div>

          <form id="login">
            <div class="auth-field-group">
              <label for="login-email" class="auth-field-label">Email akun pusat</label>
              <div class="auth-input-wrap">
                <div class="auth-input-icon-left">
                  <i class="fas fa-envelope"></i>
                </div>
                <input id="login-email" name="email" type="text" class="auth-input-control" placeholder="nama@student.umi.ac.id" autocomplete="username" required>
              </div>
            </div>

            <div class="auth-field-group">
              <label for="login-password" class="auth-field-label">Kata sandi</label>
              <div class="auth-input-wrap">
                <div class="auth-input-icon-left">
                  <i class="fas fa-lock"></i>
                </div>
                <input id="login-password" name="password" type="password" class="auth-input-control" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
                <button type="button" id="toggle-password" class="auth-toggle-pwd" aria-label="Tampilkan kata sandi">
                  <i class="fas fa-eye" id="toggle-password-icon"></i>
                </button>
              </div>
            </div>

            <div class="auth-options-row">
              <label class="auth-remember-check" for="remember-email">
                <input id="remember-email" type="checkbox">
                <span>Ingat email di perangkat ini</span>
              </label>
            </div>

            <button id="login-submit" class="auth-btn-primary" type="submit">
              <div class="shimmer-fx"></div>
              <span class="btn-text-content">
                <span class="auth-submit-text">LOGIN SEKARANG</span>
                <i class="fas fa-arrow-right"></i>
              </span>
            </button>
          </form>

          <div class="auth-accordion-container">
            <details class="auth-details-box" id="activation-card">
              <summary class="auth-details-summary">
                <i class="fas fa-play details-caret"></i>
                <span>Minta email aktivasi akun</span>
              </summary>
              <div class="auth-details-content">
                <p>Masukkan email akun pusat. Jika akun memenuhi syarat, instruksi aktivasi akan dikirim ke email.</p>
                <form id="activation-request">
                  <label class="sr-only" for="activation-email">Email akun untuk aktivasi</label>
                  <input id="activation-email" name="email" type="email" class="auth-details-input" maxlength="254" autocomplete="email" placeholder="nama@student.umi.ac.id" required>
                  <button type="submit" class="auth-details-submit auth-details-submit-primary">Kirim instruksi aktivasi</button>
                  <div class="account-action-status" id="activation-status" role="status" aria-live="polite"></div>
                </form>
              </div>
            </details>

            <details class="auth-details-box" id="reset-card">
              <summary class="auth-details-summary">
                <i class="fas fa-play details-caret"></i>
                <span>Lupa kata sandi?</span>
              </summary>
              <div class="auth-details-content">
                <p>Masukkan email akun pusat. Jika akun aktif, tautan untuk mengatur ulang kata sandi akan dikirim.</p>
                <form id="password-reset-request">
                  <label class="sr-only" for="reset-email">Email akun untuk reset kata sandi</label>
                  <input id="reset-email" name="email" type="email" class="auth-details-input" maxlength="254" autocomplete="email" placeholder="nama@student.umi.ac.id" required>
                  <button type="submit" class="auth-details-submit auth-details-submit-secondary">Kirim tautan reset</button>
                  <div class="account-action-status" id="reset-status" role="status" aria-live="polite"></div>
                </form>
              </div>
            </details>
          </div>

          <div class="auth-bottom-links">
            <div>
              <a href="/sumber-daya/" class="auth-link-home">
                <i class="fas fa-arrow-left"></i> Kembali ke Halaman Utama
              </a>
            </div>
            <div class="auth-copyright-text">
              <a href="https://iclabs.fikom.umi.ac.id/dev/" target="_blank" rel="noopener noreferrer" class="auth-copyright-link">&copy; ${new Date().getFullYear()} ICLabs Praktikum · Hak cipta dilindungi</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>`;

  initLoginInteractive();

  authCsrf = '';
  api('/auth/csrf').then(response => { authCsrf = response.csrf || '' }).catch(() => {
    ['#activation-status', '#reset-status'].forEach(selector => {
      const status = $(selector);
      if (status) {
        status.className = 'account-action-status is-error';
        status.textContent = 'Layanan formulir belum tersedia. Muat ulang halaman lalu coba kembali.';
      }
    });
  });

  try {
    const remembered = localStorage.getItem('iclabs-login-email');
    if (remembered) {
      $('#login-email').value = remembered;
      $('#remember-email').checked = true;
    }
  } catch { }

  $('#remember-email').onchange = () => {
    if (!$('#remember-email').checked) {
      try { localStorage.removeItem('iclabs-login-email'); } catch { }
    }
  };

  $('#login').onsubmit = async e => {
    e.preventDefault();
    const form = e.target, button = $('#login-submit'), values = Object.fromEntries(new FormData(form));
    button.disabled = true;
    button.classList.add('is-loading');
    button.setAttribute('aria-busy', 'true');
    const textSpan = button.querySelector('.auth-submit-text');
    if (textSpan) textSpan.textContent = 'Memeriksa akun…';
    const errorBox = $('#login-error');
    errorBox.innerHTML = '';
    errorBox.classList.remove('is-visible');
    try {
      await api('/login', values);
      try {
        if ($('#remember-email').checked) localStorage.setItem('iclabs-login-email', values.email);
        else localStorage.removeItem('iclabs-login-email');
      } catch { }
      await init();
    } catch (x) {
      errorBox.innerHTML = `<i class="fas fa-circle-exclamation text-lg"></i><span>${esc(x.message)}</span>`;
      errorBox.classList.add('is-visible');
    } finally {
      button.disabled = false;
      button.classList.remove('is-loading');
      button.removeAttribute('aria-busy');
      if (textSpan) textSpan.textContent = 'LOGIN SEKARANG';
    }
  };

  const bindAccountRequest = (formId, route, statusId) => {
    $(`#${formId}`).onsubmit = async e => {
      e.preventDefault();
      const form = e.target, button = form.querySelector('[type=submit]'), status = $(`#${statusId}`), values = Object.fromEntries(new FormData(form));
      status.className = 'account-action-status';
      if (!authCsrf) {
        status.classList.add('is-error');
        status.textContent = 'Sesi keamanan belum siap. Muat ulang halaman lalu coba kembali.';
        return;
      }
      button.disabled = true;
      button.classList.add('is-loading');
      button.setAttribute('aria-busy', 'true');
      status.classList.add('is-pending');
      status.textContent = 'Mengirim permintaan…';
      try {
        const response = await api(route, values);
        status.className = 'account-action-status is-success';
        status.textContent = response.message || 'Jika email terdaftar dan akun memenuhi syarat, instruksi akan dikirim ke email tersebut.';
        form.reset();
      } catch (error) {
        status.className = 'account-action-status is-error';
        status.textContent = error.message || 'Permintaan belum dapat diproses. Coba kembali nanti.';
      } finally {
        button.disabled = false;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');
      }
    };
  };

  bindAccountRequest('activation-request', '/auth/activation-request', 'activation-status');
  bindAccountRequest('password-reset-request', '/auth/password-reset-request', 'reset-status');
  if (typeof window.startIclabsIntro === 'function') window.startIclabsIntro();
}
async function init() { try { user = await api('/me'); activePeriod = await api('/active-period'); classes = user.role === 'mahasiswa' ? [] : await api('/classes'); current = null; changes.clear(); page = user.api_mode ? (user.role === 'mahasiswa' ? 'Nilai Saya' : 'Ringkasan') : (user.role === 'mahasiswa' ? 'Nilai Saya' : 'Dashboard'); render() } catch (e) { if (!user) { if (!$('#login')) login(); if (e.message && e.message !== 'Silakan login.' && $('#login-error')) { $('#login-error').innerHTML = `<i class="fas fa-circle-exclamation text-lg"></i><span>${esc(e.message)}</span>`; $('#login-error').classList.add('is-visible') } return } app.innerHTML = `<main class="iclabs-login auth-page"><section class="login-panel auth-form-panel auth-fallback-panel" aria-labelledby="api-fallback-title"><div class="login-heading"><h1 id="api-fallback-title">Layanan belum terhubung</h1><p>Data Praktikum belum dapat dimuat dari API pusat.</p></div><div class="error" role="alert">${esc(e.message || 'Fitur ini belum tersedia melalui API pusat.')}</div><div class="auth-fallback-actions"><button class="login-submit" type="button" onclick="init()">Coba lagi <span aria-hidden="true">→</span></button><button class="auth-secondary-action" type="button" onclick="logout()">Keluar dari akun</button></div></section></main>` } }
function iconSvg(name, cls = '') {
  const s = 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" aria-hidden="true"';
  const c = cls ? `class="svg-icon ${cls}"` : 'class="svg-icon"';
  const svgs = {
    'grid': '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    'calendar': '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
    'check-square': '<path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'shield-check': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
    'bar-chart': '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
    'settings': '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
    'clock': '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'refresh': '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
    'percent': '<line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
    'users': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'logout': '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
    'menu': '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
    'chevron-down': '<path d="m6 9 6 6 6-6"/>',
    'search': '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'close': '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'check': '<path d="M20 6 9 17l-5-5"/>',
    'arrow-right': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    'arrow-left': '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
    'external-link': '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
    'plus': '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'upload': '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
    'download': '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
    'edit': '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    'sliders': '<line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/><line x1="2" x2="6" y1="14" y2="14"/><line x1="10" x2="14" y1="8" y2="8"/><line x1="18" x2="22" y1="16" y2="16"/>',
    'trash': '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    'send': '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
    'check-circle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
    'rotate-ccw': '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    'lock': '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'save': '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
    'user-plus': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
    'user-check': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
    'repeat': '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
    'sparkles': '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
    'layers': '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
    'map-pin': '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    'award': '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
    'table': '<path d="M12 3v18"/><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/>',
    'folder-open': '<path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/>'
  };
  return `<svg ${c} viewBox="0 0 24 24" width="16" height="16" ${s}>${svgs[name] || svgs.grid}</svg>`;
}
const iconMap = { 'Nilai Saya': 'file-text', 'Dashboard': 'grid', 'Jadwal Praktikum': 'calendar', 'Absensi': 'check-square', 'Penilaian': 'file-text', 'Verifikasi': 'shield-check', 'Laporan': 'bar-chart', 'Aktivitas & Presensi': 'check-square', 'Hasil Penilaian': 'file-text', 'Kelas Praktikum': 'grid', 'Ringkasan': 'grid', 'Data Master': 'settings', 'Konfigurasi Nilai': 'percent', 'Periode Akademik': 'clock', 'Sinkron Jadwal': 'refresh', 'Log Aktivitas': 'clock', 'Manajemen Akses': 'users' };
const icons = new Proxy(iconMap, { get: (t, k) => iconSvg(t[k] || 'grid') });
function render() {
  if (user?.api_mode) { renderApiMode(); return } const nav = user.role === 'mahasiswa' ? ['Nilai Saya'] : Object.keys(iconMap).filter(x => x !== 'Nilai Saya' && (user.role === 'admin' || !['Data Master', 'Log Aktivitas'].includes(x))); app.innerHTML = `<div class="app"><aside class="sidebar">${brand()}<div class="navlabel">Navigasi</div><nav class="nav">${nav.map(x => `<button data-nav="${x}" class="${page === x ? 'active' : ''}"><span class="icon">${icons[x]}</span><span>${x}</span></button>`).join('')}</nav><div class="sidebar-footer"><div class="sidebar-status-card"><span class="system-status-indicator"><span class="status-dot-pulse"></span><span>Sistem Aktif</span></span></div><button id="logout" class="sidebar-logout-btn">${iconSvg('logout')} <span>Keluar</span></button></div></aside><div class="content"><header class="topbar"><div class="topbar-context"><button class="mobile" id="menu" aria-label="Buka menu">${iconSvg('menu')}</button><div class="topbar-breadcrumbs"><span class="topbar-crumb-app">ICLabs Praktikum</span><span class="topbar-crumb-sep">/</span><span class="topbar-crumb-page" id="topbar-current-page">${page}</span></div></div><div class="profile"><div class="avatar">${esc(user.name.split(' ').map(x => x[0]).slice(0, 2).join(''))}</div><button id="account" aria-label="Akun dan ganti password"><span class="profile-name"><strong>${esc(user.name)}</strong><small>${roleLabel[user.role]}</small></span>${iconSvg('chevron-down')}</button><button id="header-logout" class="logout-button" aria-label="Logout dari akun">${iconSvg('logout')} <span>Keluar</span></button></div></header><main class="workspace"><div id="period-summary">${periodSummary()}</div><div id="view"></div></main></div></div>`;
  document.querySelectorAll('[data-nav]').forEach(b => b.onclick = () => navigate(b.dataset.nav)); $('#logout').onclick = logout; $('#header-logout').onclick = logout; $('#account').onclick = account; $('.profile .avatar').onclick = account; $('#menu').onclick = () => $('.sidebar').classList.toggle('open'); if (user.role === 'mahasiswa') studentPortal(); else if (current) renderClass(); else if (page === 'Dashboard') dashboard(); else if (page === 'Data Master') masters(); else if (page === 'Log Aktivitas') auditPage(); else listPage()
}
function renderApiMode() {
  const student = user.role === 'mahasiswa', hasStudentRole = (user.roles || []).includes('MAHASISWA');
  const canManageAccess = Boolean(user.can_manage_access || user.role === 'admin' || (user.roles || []).some(r => ['SUPER_ADMIN', 'ADMIN_ICLABS', 'KEPALA_LAB', 'LABORAN'].includes(r)));
  const nav = student ? ['Nilai Saya'] : (user.role === 'admin' ? ['Ringkasan', 'Kelas Praktikum', 'Sinkron Jadwal', 'Periode Akademik', 'Konfigurasi Nilai', 'Log Aktivitas'] : (user.role === 'dosen' ? ['Ringkasan', 'Aktivitas & Presensi', 'Hasil Penilaian'] : ['Ringkasan', 'Kelas Praktikum']));
  if (canManageAccess && !nav.includes('Manajemen Akses')) nav.push('Manajemen Akses');
  if (!student && hasStudentRole) nav.push('Nilai Saya');
  const initials = (user.name || user.email || 'IC').split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase();
  app.innerHTML = `<div class="app"><aside class="sidebar">${brand()}<div class="navlabel">Navigasi</div><nav class="nav">${nav.map(x => `<button data-api-nav="${x}" class="${page === x ? 'active' : ''}"><span class="icon">${icons[x]}</span><span>${x}</span></button>`).join('')}</nav><div class="sidebar-footer"><div class="sidebar-status-card"><span class="system-status-indicator"><span class="status-dot-pulse"></span><span>Mode API Terhubung</span></span></div><button id="api-logout" class="sidebar-logout-btn">${iconSvg('logout')} <span>Keluar</span></button></div></aside><div class="content"><header class="topbar"><div class="topbar-context"><button class="mobile" id="api-menu" aria-label="Buka menu">${iconSvg('menu')}</button><div class="topbar-breadcrumbs"><span class="topbar-crumb-app">ICLabs Praktikum</span><span class="topbar-crumb-sep">/</span><span class="topbar-crumb-page" id="topbar-current-page">${page}</span><span class="badge badge-api-indicator">API Pusat</span></div></div><div class="profile"><div class="avatar">${esc(initials)}</div><button id="api-account" aria-label="Akun dan ganti password"><span class="profile-name"><strong>${esc(user.name)}</strong><small>${roleLabel[user.role]}</small></span>${iconSvg('chevron-down')}</button><button id="api-header-logout" class="logout-button" aria-label="Logout dari akun">${iconSvg('logout')} <span>Keluar</span></button></div></header><main class="workspace"><div id="api-period-summary">${apiPeriodSummary()}</div><div id="api-view"></div></main></div></div>`;
  document.querySelectorAll('[data-api-nav]').forEach(b => b.onclick = () => { if (window.apiDraftStorageFailed && !confirm('Draft presensi gagal disimpan pada perangkat ini. Tetap tinggalkan halaman?')) return; window.apiDraftStorageFailed = false; page = b.dataset.apiNav; apiCurrent = null; window.selectedStudentClassId = null; renderApiMode() }); $('#api-logout').onclick = logout; $('#api-header-logout').onclick = logout; $('#api-account').onclick = account; $('#api-menu').onclick = () => $('.sidebar').classList.toggle('open');
  if (student || page === 'Nilai Saya') apiStudentGrades(); else if (apiCurrent) apiClassDetailWithEntry(apiCurrent); else if (['Kelas Praktikum', 'Aktivitas & Presensi', 'Hasil Penilaian'].includes(page)) apiClassListPage(); else if (page === 'Sinkron Jadwal') googleSheetScheduleSyncPage(); else if (page === 'Periode Akademik') periodManagementPage(); else if (page === 'Konfigurasi Nilai') gradeConfigPage(); else if (page === 'Log Aktivitas') auditPage(); else if (page === 'Manajemen Akses') accessManagementPage(); else apiDashboard();
}
let googleSheetReview = null, googleSheetRenderTimer = null;
let googleSheetAssistantRecommendationTimer = null;
const googleSheetReviewFields = [['prodi', 'Prodi'], ['hari', 'Hari'], ['jam', 'Jam'], ['kode_mk', 'Kode MK'], ['mata_kuliah', 'Mata Kuliah'], ['dosen', 'Dosen'], ['frekuensi', 'Frekuensi'], ['kelas', 'Kelas (opsional)'], ['laboratorium', 'Laboratorium'], ['asisten_1', 'Asisten 1'], ['asisten_2', 'Asisten 2'], ['asisten_3', 'Asisten 3/Pendamping'], ['peserta', 'Peserta'], ['sks', 'SKS']];
const googleSheetMappedFields = {
  prodi: { label: 'Program Studi', ref: 'program_studi', idKey: 'id', valueKey: 'kode', aliases: ['kode', 'nama'] },
  kode_mk: { label: 'Mata Kuliah', ref: 'mata_kuliah', idKey: 'id', valueKey: 'kode', aliases: ['kode', 'nama'] },
  laboratorium: { label: 'Laboratorium', ref: 'laboratorium', idKey: 'id', valueKey: 'nama', aliases: ['nama', 'kode'] },
  dosen: { label: 'Dosen', ref: 'dosen', idKey: 'id_user', valueKey: 'nama', idField: 'id_user_dosen', aliases: ['nama', 'email'], staff: true, required: true },
  asisten_1: { label: 'Asisten 1', ref: 'asisten', idKey: 'id_user', valueKey: 'nama', idField: 'id_user_asisten_utama', aliases: ['nama', 'email'], staff: true, required: true },
  asisten_2: { label: 'Asisten 2', ref: 'asisten', idKey: 'id_user', valueKey: 'nama', idField: 'id_user_asisten_pengajar_pendukung', aliases: ['nama', 'email'], staff: true, optional: true },
  asisten_3: { label: 'Asisten 3/Pendamping', ref: 'asisten', idKey: 'id_user', valueKey: 'nama', idField: 'id_user_pendamping_teknis', aliases: ['nama', 'email'], staff: true, optional: true }
};
function sheetNorm(value) { return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[\u200B-\u200D\uFEFF]/g, '').toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim().replace(/\s+/g, ' ') }
function sheetPersonNorm(value) { return sheetNorm(String(value ?? '').split('/')[0].replace(/\b(?:prof|dr|ir)\.?\s*/gi, '').split(',')[0]) }
function sheetDistance(a, b) { const aa = Array.from(a), bb = Array.from(b); if (!aa.length || !bb.length) return Math.max(aa.length, bb.length); let row = bb.map((_, i) => i + 1); for (let i = 0; i < aa.length; i++) { let previous = i; const next = []; for (let j = 0; j < bb.length; j++) { const current = Math.min((next[j - 1] ?? (i + 1)), row[j] + 1, previous + (aa[i] === bb[j] ? 0 : 1)); next.push(current); previous = row[j] } row = next } return row[bb.length - 1] }
function sheetOptions(field) { return googleSheetReview?.references?.[googleSheetMappedFields[field]?.ref] || [] }
function sheetMatchingOptions(field) { const options = sheetOptions(field); if (field !== 'asisten_3') return options; return options.filter(option => String(option?.kode_tingkat_asisten || option?.tingkat_asisten?.kode || '').trim().toUpperCase() === 'ASISTEN_3') }
function sheetMatchNorm(field, value) { const normalized = sheetNorm(value); return field === 'laboratorium' ? normalized.replace(/^lab\s+/, '') : normalized }
function sheetCanonical(option, field) { return String(option?.[googleSheetMappedFields[field]?.valueKey] ?? '') }
function sheetOptionLabel(option, field) { const config = googleSheetMappedFields[field]; if (config.staff) return `${option.nama || 'Tanpa nama'} · ${option.email || `ID ${option.id_user}`}${option.kode_tingkat_asisten ? ` · ${option.nama_tingkat_asisten || option.kode_tingkat_asisten}` : ''}`; return `${option.nama || option.kode || ''}${option.kode && option.nama ? ` · ${option.kode}` : ''}` }
function sheetExactMatches(field, raw, options) { const config = googleSheetMappedFields[field], needle = sheetMatchNorm(field, raw), personNeedle = config?.staff ? sheetPersonNorm(raw) : ''; if (!config || !needle) return []; return options.filter(option => config.aliases.some(alias => { const value = String(option?.[alias] ?? ''); return sheetMatchNorm(field, value) === needle || (config.staff && alias === 'nama' && personNeedle !== '' && sheetPersonNorm(value) === personNeedle) })) }
function sheetExactOptions(field, raw) { return sheetExactMatches(field, raw, sheetMatchingOptions(field)) }
function sheetSimilarOptions(field, raw) { const config = googleSheetMappedFields[field], needle = sheetMatchNorm(field, raw), personNeedle = config?.staff ? sheetPersonNorm(raw) : needle; if (!config || !needle) return []; return sheetMatchingOptions(field).map(option => { const candidates = config.aliases.map(alias => sheetMatchNorm(field, option?.[alias] ?? '')).filter(Boolean); if (config.staff) candidates.push(sheetPersonNorm(option?.nama ?? '')); const tokens = candidates.flatMap(value => value.split(' ')); let score = 0; for (const candidate of candidates) { const distance = sheetDistance(personNeedle, candidate); score = Math.max(score, 1 - distance / Math.max(personNeedle.length, candidate.length, 1)); if (candidate.includes(personNeedle) || personNeedle.includes(candidate)) score = Math.max(score, .72); if (config.staff && tokens.includes(needle)) score = Math.max(score, .96) } return { id: String(option[config.idKey]), option, score } }).filter(item => item.score >= .38).sort((a, b) => b.score - a.score || sheetOptionLabel(a.option, field).localeCompare(sheetOptionLabel(b.option, field))).slice(0, 6) }
function sheetEmptyOptional(value) { return ['', '-', '–', '—', 'n/a', 'na', 'belum terisi', 'belum diisi', 'kosong'].includes(sheetNorm(value)) }
function sheetSetMapping(row, field, option) { const config = googleSheetMappedFields[field]; if (!config) return; delete row._suggested[field]; if (!option) { delete row._selected[field]; if (config.idField) row[config.idField] = null; row[field] = config.optional ? '' : ''; return } row._selected[field] = { id: String(option[config.idKey]), option }; row[field] = sheetCanonical(option, field); if (config.idField) row[config.idField] = Number(option[config.idKey]) }
function sheetInitializeMappings(rows, references) { googleSheetReview = { rows, references }; for (const row of rows) { row._selected = {}; row._suggested = {}; for (const field of Object.keys(googleSheetMappedFields)) { const config = googleSheetMappedFields[field], raw = String(row[field] ?? '').trim(); if (config.optional && sheetEmptyOptional(raw)) { row[field] = ''; continue } const exact = sheetExactOptions(field, raw); if (exact.length === 1) sheetSetMapping(row, field, exact[0]); else { const similar = sheetSimilarOptions(field, raw); if (similar.length) row._suggested[field] = { ...similar[0], raw } } } } }
function sheetEffectiveStaffOption(row, field) {
  const config = googleSheetMappedFields[field], selected = row._selected?.[field], raw = String(row[field] ?? '').trim();
  if (selected && String(selected.id) === String(selected.option?.[config.idKey])) return selected.option;
  const exact = sheetExactOptions(field, raw);
  if (exact.length === 1) return exact[0];
  return null;
}
function sheetTimeRange(row) {
  const match = String(row.jam ?? '').match(/^\s*(\d{1,2})[.:](\d{2})\s*[-–—]\s*(\d{1,2})[.:](\d{2})\s*$/u);
  if (!match) return null;
  const start = Number(match[1]) * 60 + Number(match[2]), end = Number(match[3]) * 60 + Number(match[4]);
  if (Number(match[1]) > 23 || Number(match[3]) > 23 || Number(match[2]) > 59 || Number(match[4]) > 59 || end <= start) return null;
  return { start, end };
}
function sheetSchedulesOverlap(left, right) {
  if (sheetNorm(left.hari) !== sheetNorm(right.hari)) return false;
  const leftTime = sheetTimeRange(left), rightTime = sheetTimeRange(right);
  return Boolean(leftTime && rightTime && leftTime.start < rightTime.end && rightTime.start < leftTime.end);
}
function sheetScheduleConflicts() {
  const rows = googleSheetReview?.rows || [], conflicts = new Map(), staffFields = ['dosen', 'asisten_1', 'asisten_2', 'asisten_3'];
  const add = (row, field, message) => { if (!conflicts.has(row)) conflicts.set(row, new Map()); const fields = conflicts.get(row); if (!fields.has(field)) fields.set(field, []); if (!fields.get(field).includes(message)) fields.get(field).push(message) };
  const assignments = rows.map(row => ({ row, staff: staffFields.map(field => ({ field, option: sheetEffectiveStaffOption(row, field) })).filter(item => item.option && item.option.id_user !== undefined && item.option.id_user !== null), room: (() => { const option = sheetEffectiveStaffOption(row, 'laboratorium'); return option?.id_ruang === undefined ? null : String(option.id_ruang) })() }));
  for (const entry of assignments) {
    const byUser = new Map();
    for (const item of entry.staff) { const id = String(item.option.id_user); if (!byUser.has(id)) byUser.set(id, []); byUser.get(id).push(item) }
    for (const sameUser of byUser.values()) if (sameUser.length > 1) { const roles = sameUser.map(item => googleSheetMappedFields[item.field].label).join(' dan '); for (const item of sameUser) add(entry.row, item.field, `Akun yang sama dipilih untuk ${roles} pada jadwal ini.`) }
  }
  for (let i = 0; i < assignments.length; i++)for (let j = i + 1; j < assignments.length; j++) {
    const left = assignments[i], right = assignments[j];
    if (!sheetSchedulesOverlap(left.row, right.row)) continue;
    if (left.room && right.room && left.room === right.room) {
      add(left.row, 'laboratorium', `Ruangan bertabrakan dengan jadwal pada baris ${Number(right.row.baris_sumber)}.`);
      add(right.row, 'laboratorium', `Ruangan bertabrakan dengan jadwal pada baris ${Number(left.row.baris_sumber)}.`);
    }
  }
  return conflicts;
}
function sheetScheduleWarnings() {
  const rows = googleSheetReview?.rows || [], warnings = new Map(), staffFields = ['dosen', 'asisten_1', 'asisten_2', 'asisten_3'];
  const add = (row, field, message) => { if (!warnings.has(row)) warnings.set(row, new Map()); const fields = warnings.get(row); if (!fields.has(field)) fields.set(field, []); if (!fields.get(field).includes(message)) fields.get(field).push(message) };
  const assignments = rows.map(row => ({ row, staff: staffFields.map(field => ({ field, option: sheetEffectiveStaffOption(row, field) })).filter(item => item.option && item.option.id_user !== undefined && item.option.id_user !== null) }));
  for (let i = 0; i < assignments.length; i++)for (let j = i + 1; j < assignments.length; j++) {
    const left = assignments[i], right = assignments[j]; if (!sheetSchedulesOverlap(left.row, right.row)) continue;
    for (const leftStaff of left.staff) for (const rightStaff of right.staff) {
      if (String(leftStaff.option.id_user) !== String(rightStaff.option.id_user)) continue;
      if (leftStaff.field === 'dosen' && rightStaff.field === 'dosen') continue;
      if (leftStaff.field.startsWith('asisten_')) add(left.row, leftStaff.field, `Jadwal Asisten bertabrakan dengan baris ${Number(right.row.baris_sumber)} (${googleSheetMappedFields[rightStaff.field].label}). Tetap dapat disimpan.`);
      if (rightStaff.field.startsWith('asisten_')) add(right.row, rightStaff.field, `Jadwal Asisten bertabrakan dengan baris ${Number(left.row.baris_sumber)} (${googleSheetMappedFields[leftStaff.field].label}). Tetap dapat disimpan.`);
    }
  }
  return warnings;
}
function sheetRowWarnings(row, warnings = null) {
  if (!warnings) warnings = sheetScheduleWarnings();
  const result = [];
  for (const field of ['asisten_1', 'asisten_2', 'asisten_3']) {
    result.push(...(warnings.get(row)?.get(field) || []));
    const selectedAvailability = row._assistantAvailability?.[field];
    result.push(...(selectedAvailability?.peringatan || []).map(item => String(item.pesan || '')).filter(Boolean));
  }
  return [...new Set(result)];
}
function sheetAssistantAvailabilityLabel(candidate) {
  const code = String(candidate?.kode_ketersediaan || '');
  if (code === 'TERSEDIA') return 'Tersedia';
  if (code === 'JADWAL_BELUM_DIKIRIM') return 'Jadwal pribadi belum dikirim';
  const warning = (candidate?.peringatan || []).map(item => item.pesan).filter(Boolean)[0];
  return warning ? `${warning}` : 'Bentrok jadwal';
}
function sheetWeekdayNumber(value) { return ({ senin: 1, selasa: 2, rabu: 3, kamis: 4, jumat: 5, sabtu: 6, minggu: 7 })[sheetNorm(value)] || null }
function sheetAssistantRecommendationPayload() {
  const periodId = Number(activePeriod?.id || 0), rows = [];
  for (const row of googleSheetReview?.rows || []) {
    const prodi = row._selected?.prodi?.option, kursus = row._selected?.kode_mk?.option, time = sheetTimeRange(row), weekday = sheetWeekdayNumber(row.hari);
    if (!periodId || !prodi || !kursus || !String(row.frekuensi || '').trim() || !time || !weekday) continue;
    rows.push({
      baris_sumber: Number(row.baris_sumber), prodi: String(prodi.kode || ''), kode_mk: String(kursus.kode || ''), frekuensi: String(row.frekuensi).trim(), hari_ke: weekday,
      jam_mulai: `${String(Math.floor(time.start / 60)).padStart(2, '0')}:${String(time.start % 60).padStart(2, '0')}`,
      jam_selesai: `${String(Math.floor(time.end / 60)).padStart(2, '0')}:${String(time.end % 60).padStart(2, '0')}`,
      id_user_dosen: row._selected?.dosen ? Number(row._selected.dosen.id) : null,
      id_user_asisten_utama: row._selected?.asisten_1 ? Number(row._selected.asisten_1.id) : null,
      id_user_asisten_pengajar_pendukung: row._selected?.asisten_2 ? Number(row._selected.asisten_2.id) : null,
      id_user_pendamping_teknis: row._selected?.asisten_3 ? Number(row._selected.asisten_3.id) : null
    });
  }
  return { periodId, rows };
}
let googleSheetAssistantRecommendationSequence = 0;
async function requestGoogleSheetAssistantRecommendations() {
  const state = googleSheetReview; if (!state) return;
  const requestId = ++googleSheetAssistantRecommendationSequence, { periodId, rows } = sheetAssistantRecommendationPayload();
  for (const row of state.rows) { row._assistantAvailability = {}; row._assistantCandidates = {} }
  if (!rows.length) { state.assistantMatching = false; state.assistantMessage = 'Lengkapi pemetaan Program Studi, Mata Kuliah, frekuensi, hari, dan jam untuk melihat rekomendasi Asisten.'; renderGoogleSheetReview(false); return }
  state.assistantMatching = true; state.assistantMessage = 'Memeriksa jadwal pribadi dan penugasan Praktikum Asisten…'; renderGoogleSheetReview(false);
  try {
    const result = await api('/admin/recommend-google-sheet-assistants', { id_periode_akademik: periodId, jadwal: rows }, 'POST');
    if (requestId !== googleSheetAssistantRecommendationSequence || state !== googleSheetReview) return;
    const references = new Map((state.references?.asisten || []).map(person => [String(person.id_user), person]));
    for (const recommendation of result.baris || []) {
      const row = state.rows.find(item => Number(item.baris_sumber) === Number(recommendation.baris_sumber)); if (!row) continue;
      for (const [field, slotCode] of [['asisten_1', 'ASISTEN_UTAMA'], ['asisten_2', 'ASISTEN_PENGAJAR_PENDUKUNG'], ['asisten_3', 'PENDAMPING_TEKNIS']]) {
        const slot = recommendation.slot?.[slotCode]; if (!slot) continue;
        row._assistantAvailability[field] = slot.terpilih || null;
        row._assistantCandidates[field] = (slot.kandidat || []).map(candidate => ({ candidate, option: references.get(String(candidate.id_user)) })).filter(item => item.option);
      }
    }
    state.assistantMessage = `Rekomendasi dimuat untuk ${result.baris?.length || 0} baris. Bentrok Asisten adalah peringatan; pilihan tetap ditentukan admin.`;
  } catch (error) {
    if (requestId === googleSheetAssistantRecommendationSequence && state === googleSheetReview) state.assistantMessage = `Rekomendasi Asisten tidak tersedia: ${error.message} Sinkronisasi manual tetap dapat dilanjutkan.`;
  } finally {
    if (requestId === googleSheetAssistantRecommendationSequence && state === googleSheetReview) { state.assistantMatching = false; renderGoogleSheetReview(false) }
  }
}
function sheetFieldStatus(row, field, conflicts = null) {
  const config = googleSheetMappedFields[field], raw = String(row[field] ?? '').trim();
  if (!conflicts) conflicts = sheetScheduleConflicts();
  if (config.optional && sheetEmptyOptional(raw)) return { state: 'empty', text: 'Opsional · kosong', suggestions: [] };
  let status;
  if (!raw) status = { state: config.required ? 'missing' : 'empty', text: config.required ? 'Wajib dipilih' : 'Opsional · kosong', suggestions: [] };
  else {
    const selected = row._selected?.[field];
    if (selected && String(selected.id) === String(selected.option?.[config.idKey])) status = { state: 'valid', text: 'Master cocok', suggestions: [] };
    else {
      const exact = sheetExactOptions(field, raw);
      if (exact.length === 1) { sheetSetMapping(row, field, exact[0]); status = { state: 'valid', text: 'Cocok tepat', suggestions: [] } }
      else {
        const ranked = sheetSimilarOptions(field, raw), stored = row._suggested?.[field], storedOption = stored?.raw === raw && stored && sheetMatchingOptions(field).find(option => String(option[config.idKey]) === String(stored.option?.[config.idKey])), suggestions = storedOption ? [stored, ...ranked.filter(item => String(item.option[config.idKey]) !== String(stored.id))] : ranked;
        if (suggestions.length) row._suggested[field] = { ...suggestions[0], raw };
        const wrongAssistantTier = field === 'asisten_3' && !suggestions.length && sheetExactMatches(field, raw, sheetOptions(field)).length > 0, primary = suggestions[0];
        const text = primary ? (primary.source === 'ai' ? `Estimasi AI · ${Number(primary.confidence) || 0}%` : `Saran terpilih · ${primary.option.nama || primary.option.kode || ''}`) : wrongAssistantTier ? 'Nama ditemukan, tetapi jenjangnya bukan Asisten 3/Pendamping' : 'Tidak cocok dengan master';
        status = { state: suggestions.length ? 'review' : 'missing', text, suggestions };
      }
    }
  }
  const messages = conflicts.get(row)?.get(field) || [];
  if (messages.length) return { ...status, state: 'conflict', text: messages.join(' ') };
  return status;
}
function sheetRowIssues(row, conflicts = null) {
  if (!conflicts) conflicts = sheetScheduleConflicts();
  const issues = [];
  for (const field of Object.keys(googleSheetMappedFields)) { const status = sheetFieldStatus(row, field, conflicts); if (['review', 'missing', 'conflict'].includes(status.state)) issues.push({ field, state: status.state, label: status.state === 'conflict' ? status.text : undefined }) }
  for (const [field, label] of [['hari', 'Hari'], ['jam', 'Jam'], ['frekuensi', 'Frekuensi'], ['peserta', 'Peserta'], ['sks', 'SKS']]) if (!String(row[field] ?? '').trim()) issues.push({ field, state: 'missing', label: `${label} wajib diisi` });
  if (String(row.frekuensi ?? '').trim() && !/^[A-Z0-9]+_[A-Z0-9]+-[1-9]\d*$/i.test(String(row.frekuensi).trim())) issues.push({ field: 'frekuensi', state: 'missing', label: 'Gunakan format frekuensi seperti TI_ALPRO-1' });
  if (String(row.jam ?? '').trim() && !/^\s*\d{1,2}[.:]\d{2}\s*[-–]\s*\d{1,2}[.:]\d{2}\s*$/.test(String(row.jam))) issues.push({ field: 'jam', state: 'missing', label: 'Format jam, contoh 08.00-10.20' });
  if (String(row.peserta ?? '').trim() && !/^\d+(?:\s*\+\s*\d+)*$/.test(String(row.peserta).trim())) issues.push({ field: 'peserta', state: 'missing', label: 'Gunakan angka atau format informasi seperti 17+5' });
  return issues;
}
function sheetReadySummary(conflicts = null) {
  if (!conflicts) conflicts = sheetScheduleConflicts();
  const rows = googleSheetReview?.rows || []; let valid = 0, review = 0, conflict = 0, invalid = 0, warning = 0; const advisory = sheetScheduleWarnings();
  for (const row of rows) { const issues = sheetRowIssues(row, conflicts); if (!issues.length) valid++; else if (issues.some(issue => issue.state === 'conflict')) conflict++; else if (issues.some(issue => issue.state === 'review')) review++; else invalid++ }
  for (const row of rows) if (sheetRowWarnings(row, advisory).length) warning++;
  return { valid, review, conflict, invalid, warning, total: rows.length, ready: rows.length > 0 && valid === rows.length };
}
function sheetPendingSuggestions(conflicts = null) {
  if (!conflicts) conflicts = sheetScheduleConflicts();
  const pending = []; for (const row of googleSheetReview?.rows || []) for (const field of Object.keys(googleSheetMappedFields)) { const status = sheetFieldStatus(row, field, conflicts); if (status.state === 'review' && status.suggestions[0]) pending.push({ row, field, option: status.suggestions[0].option }) } return pending;
}

function confirmAllSheetSuggestions() { const pending = sheetPendingSuggestions(); if (!pending.length) return; if (!confirm(`Konfirmasi ${pending.length} kandidat teratas yang disarankan? Ini hanya menerima pemetaan berwarna oranye dan belum menyimpan jadwal. Kandidat yang salah masih dapat diedit sebelum tombol Simpan.`)) return; for (const item of pending) sheetSetMapping(item.row, item.field, item.option); renderGoogleSheetReview(false); requestGoogleSheetAssistantRecommendations(); toast(`${pending.length} kandidat dikonfirmasi. Periksa tabel; data belum disimpan.`) }
function googleSheetMapSelect(row, index, field, conflicts = null) {
  const config = googleSheetMappedFields[field], status = sheetFieldStatus(row, field, conflicts), options = sheetMatchingOptions(field), suggested = status.suggestions.map(item => item.option), selected = row._selected?.[field]?.id || status.suggestions[0]?.id || '', primary = status.suggestions[0], assistantCandidates = row._assistantCandidates?.[field] || [], apiOptions = assistantCandidates.map(item => item.option), apiIds = new Set(apiOptions.map(option => String(option[config.idKey]))), suggestedIds = new Set(suggested.map(option => String(option[config.idKey]))), sorted = [...apiOptions, ...suggested.filter(option => !apiIds.has(String(option[config.idKey]))), ...options.filter(option => !apiIds.has(String(option[config.idKey])) && !suggestedIds.has(String(option[config.idKey]))).sort((a, b) => sheetOptionLabel(a, field).localeCompare(sheetOptionLabel(b, field)))], selectedAvailability = row._assistantAvailability?.[field], localWarnings = sheetScheduleWarnings().get(row)?.get(field) || [], selectedWarnings = [...(selectedAvailability?.peringatan || []).map(item => item.pesan || ''), ...localWarnings], uniqueWarnings = [...new Set(selectedWarnings.filter(Boolean))];
  const statusLine = uniqueWarnings.length ? uniqueWarnings.join(' ') : selectedAvailability ? sheetAssistantAvailabilityLabel(selectedAvailability) : status.text;
  return `<div class="gs-map gs-${status.state} ${uniqueWarnings.length ? 'gs-map-warning' : ''}"><select aria-label="Pilih master ${esc(config.label)} baris ${Number(row.baris_sumber)}" data-sheet-map="${field}" data-sheet-row="${index}"><option value="">${config.optional ? 'Kosong / tidak ditugaskan' : 'Pilih master canonical…'}</option>${sorted.map((option, i) => { const apiCandidate = assistantCandidates.find(item => String(item.option[config.idKey]) === String(option[config.idKey]))?.candidate, label = apiCandidate ? `${sheetOptionLabel(option, field)} · ${sheetAssistantAvailabilityLabel(apiCandidate)}` : `${suggestedIds.has(String(option[config.idKey])) ? `Saran ${i + 1}: ` : ''}${sheetOptionLabel(option, field)}`; return `<option value="${esc(option[config.idKey])}" ${String(option[config.idKey]) === String(selected) ? 'selected' : ''}>${esc(label)}</option>` }).join('')}</select><small class="gs-status ${uniqueWarnings.length || status.state === 'conflict' ? 'gs-status-error' : ''}">${esc(statusLine)}</small>${primary?.source === 'ai' && primary.reason ? `<small class="gs-review-note">Alasan AI: ${esc(primary.reason)}</small>` : ''}${status.state === 'review' ? `<small class="gs-review-note">Kandidat usulan belum dikonfirmasi. Periksa sebelum menerima.</small><button type="button" class="gs-confirm-match" data-sheet-confirm="${field}" data-sheet-row="${index}">Konfirmasi pilihan</button>` : ''}</div>`;
}
document.addEventListener('click', event => { const button = event.target.closest('[data-sheet-confirm]'); if (!button) return; const row = googleSheetReview?.rows[Number(button.dataset.sheetRow)], field = button.dataset.sheetConfirm, config = googleSheetMappedFields[field], value = button.closest('.gs-map')?.querySelector('[data-sheet-map]')?.value; if (!row || !config || !value) return; const option = sheetMatchingOptions(field).find(item => String(item[config.idKey]) === String(value)); if (!option) return; sheetSetMapping(row, field, option); renderGoogleSheetReview(false); requestGoogleSheetAssistantRecommendations() });
function renderGoogleSheetReview(preserve) {
  const state = googleSheetReview, host = $('#google-sheet-review');
  if (!state || !host) return;
  let focus = null;
  if (preserve && document.activeElement?.dataset?.sheetField) { focus = { row: document.activeElement.dataset.sheetRow, field: document.activeElement.dataset.sheetField, start: document.activeElement.selectionStart, end: document.activeElement.selectionEnd } }
  const conflicts = sheetScheduleConflicts(), pendingSuggestions = sheetPendingSuggestions(conflicts);
  const summary = sheetReadySummary(conflicts);
  host.innerHTML = `<div class="gs-summary"><span class="gs-count gs-count-good">${summary.valid} siap</span><span class="gs-count gs-count-review">${summary.review} perlu verifikasi</span><span class="gs-count gs-count-conflict">${summary.conflict} konflik penghalang</span><span class="gs-count gs-count-bad">${summary.invalid} belum cocok</span>${summary.warning ? `<span class="gs-count gs-count-warning">${summary.warning} baris dengan peringatan Asisten</span>` : ''}<span class="gs-total">${summary.total} baris · SHA-256 ${esc(state.checksum || '—')}</span></div><div class="help gs-guide"><span class="gs-dot gs-dot-good"></span> cocok tepat <span class="gs-dot gs-dot-review"></span> kandidat kemiripan — perlu konfirmasi <span class="gs-dot gs-dot-bad"></span> belum cocok / wajib diperbaiki. Peringatan Asisten berwarna merah tetapi tidak menghalangi simpan.</div>${summary.conflict ? `<div class="error gs-conflict-alert" role="alert"><strong>Ada konflik yang menghalangi simpan pada ${summary.conflict} baris.</strong> Konflik meliputi akun yang mengisi beberapa slot pada satu jadwal atau ruangan yang dipakai bersamaan.</div>` : ''}${summary.warning ? `<div class="error gs-assistant-warning" role="status"><strong>Peringatan ketersediaan Asisten pada ${summary.warning} baris.</strong> Bentrok jadwal pribadi atau penugasan paralel tidak memblokir simpan; admin tetap menentukan penugasan.</div>` : ''}${state.assistantMessage ? `<div class="${state.assistantMatching ? 'empty' : 'help'} gs-assistant-message" role="status">${esc(state.assistantMessage)}</div>` : ''}<div class="table-wrap gs-table-wrap"><table class="gs-table"><thead><tr><th>Baris</th>${googleSheetReviewFields.map(([, label]) => `<th>${esc(label)}</th>`).join('')}<th>Status baris</th></tr></thead><tbody>${state.rows.map((row, index) => { const issues = sheetRowIssues(row, conflicts), hasWarning = sheetRowWarnings(row).length > 0, rowState = issues.some(issue => issue.state === 'conflict') ? 'conflict' : issues.some(issue => issue.state === 'review') ? 'review' : issues.length ? 'missing' : hasWarning ? 'warning' : 'valid', statusText = rowState === 'valid' ? 'Siap disimpan' : rowState === 'warning' ? 'Peringatan Asisten · tetap dapat disimpan' : rowState === 'review' ? 'Perlu verifikasi' : rowState === 'conflict' ? 'Konflik penghalang' : 'Belum lengkap'; return `<tr class="gs-row-${rowState}"><td class="gs-line">${Number(row.baris_sumber)}</td>${googleSheetReviewFields.map(([field, label]) => { const isMapped = Boolean(googleSheetMappedFields[field]), raw = String(row[field] ?? ''); return `<td class="gs-cell"><label class="gs-mobile-label">${esc(label)}</label><input aria-label="${esc(label)} baris ${Number(row.baris_sumber)}" data-sheet-field="${field}" data-sheet-row="${index}" value="${esc(raw)}" ${field === 'frekuensi' ? 'placeholder="TI_ALPRO-1"' : ''}>${isMapped ? googleSheetMapSelect(row, index, field, conflicts) : ''}</td>` }).join('')}<td><span class="gs-row-badge gs-badge-${rowState}">${statusText}</span>${issues.filter(issue => issue.label).map(issue => `<small class="gs-status gs-status-error">${esc(issue.label)}</small>`).join('')}</td></tr>` }).join('')}</tbody></table></div><div class="row between gs-review-footer"><span class="help">Jumlah Peserta hanya informasi (contoh 17+5 → 17); roster tidak diubah. Frekuensi yang sama pada periode ini akan diperbarui.</span><button class="primary" id="google-sheet-commit" ${summary.ready ? '' : 'disabled'}>${iconSvg('save')} <span>Simpan ${summary.total} baris ke API pusat</span></button></div>`;
  host.insertAdjacentHTML('afterbegin', `<div class="gs-ai-toolbar"><div><strong>Bantuan pencocokan AI</strong><p class="help">AI hanya memberi usulan dari master pusat. Periksa dan konfirmasi kandidat; jadwal belum disimpan.</p>${state.aiMessage ? `<small class="gs-ai-message" role="status">${esc(state.aiMessage)}</small>` : ''}</div><button type="button" class="gs-ai-button" id="google-sheet-ai-match" ${state.aiMatching ? 'disabled' : ''}>${iconSvg('sparkles')} <span>${state.aiMatching ? 'Mencocokkan…' : state.aiUsed ? 'Cocokkan ulang' : 'Cocokkan dengan AI'}</span></button></div><div class="row between gs-bulk-confirm"><span class="help">Terima kandidat teratas untuk semua pemetaan oranye sekaligus. Data tetap belum tersimpan sampai tombol Simpan ditekan.</span><button type="button" class="gs-confirm-all" id="google-sheet-confirm-all" ${pendingSuggestions.length ? '' : 'disabled'}>${iconSvg('check-circle')} <span>Konfirmasi semua saran (${pendingSuggestions.length})</span></button></div>`);
  host.querySelectorAll('[data-sheet-field]').forEach(input => input.oninput = () => { const row = state.rows[Number(input.dataset.sheetRow)], field = input.dataset.sheetField; row[field] = input.value; if (googleSheetMappedFields[field]) { delete row._selected[field]; const config = googleSheetMappedFields[field]; if (config.idField) row[config.idField] = null } clearTimeout(googleSheetRenderTimer); googleSheetRenderTimer = setTimeout(() => { renderGoogleSheetReview(true); if (['prodi', 'kode_mk', 'hari', 'jam', 'frekuensi', 'dosen', 'asisten_1', 'asisten_2', 'asisten_3'].includes(field)) { clearTimeout(googleSheetAssistantRecommendationTimer); googleSheetAssistantRecommendationTimer = setTimeout(requestGoogleSheetAssistantRecommendations, 350) } }, 220) });
  host.querySelectorAll('[data-sheet-map]').forEach(select => select.onchange = () => { const row = state.rows[Number(select.dataset.sheetRow)], field = select.dataset.sheetMap, config = googleSheetMappedFields[field], option = select.value ? sheetMatchingOptions(field).find(item => String(item[config.idKey]) === select.value) : null; sheetSetMapping(row, field, option); renderGoogleSheetReview(false); if (['prodi', 'kode_mk', 'hari', 'jam', 'frekuensi', 'dosen', 'asisten_1', 'asisten_2', 'asisten_3'].includes(field)) requestGoogleSheetAssistantRecommendations() });
  $('#google-sheet-confirm-all')?.addEventListener('click', confirmAllSheetSuggestions);
  $('#google-sheet-ai-match')?.addEventListener('click', requestGoogleSheetAiMatches);
  $('#google-sheet-commit')?.addEventListener('click', commitGoogleSheetReview);
  if (focus) { const target = host.querySelector(`[data-sheet-field="${focus.field}"][data-sheet-row="${focus.row}"]`); if (target) { target.focus(); try { target.setSelectionRange(focus.start, focus.end) } catch { } } }
}
async function requestGoogleSheetAiMatches() {
  const state = googleSheetReview;
  if (!state || state.aiMatching) return;
  state.aiMatching = true;
  state.aiMessage = 'Mengirim nilai yang belum cocok ke API pusat untuk dicari kandidatnya…';
  renderGoogleSheetReview(false);
  try {
    const jadwal = state.rows.map(row => ({ baris_sumber: Number(row.baris_sumber), prodi: row.prodi, kode_mk: row.kode_mk, mata_kuliah: row.mata_kuliah, dosen: row.dosen, laboratorium: row.laboratorium, asisten_1: row.asisten_1, asisten_2: row.asisten_2, asisten_3: row.asisten_3 }));
    const result = await api('/admin/match-google-sheet-ai', { jadwal }, 'POST');
    let applied = 0;
    for (const recommendation of result.rekomendasi || []) {
      const row = state.rows.find(item => Number(item.baris_sumber) === Number(recommendation.baris_sumber)), field = recommendation.field, config = googleSheetMappedFields[field];
      if (!row || !config || row._selected?.[field]) continue;
      const option = sheetMatchingOptions(field).find(item => String(item[config.idKey]) === String(recommendation.kandidat_id));
      if (!option) continue;
      row._suggested[field] = { id: String(option[config.idKey]), option, raw: String(row[field] ?? '').trim(), source: 'ai', confidence: Number(recommendation.keyakinan_ai) || 0, reason: String(recommendation.alasan || '') };
      applied++;
    }
    state.aiUsed = true;
    const skipped = Number(result.jumlah_nilai_dilewati_batas) || 0;
    state.aiMessage = `AI memeriksa ${Number(result.jumlah_nilai_unik_diperiksa) || 0} nilai dan memberi ${applied} rekomendasi. Semua tetap perlu diperiksa.${skipped ? ` ${skipped} nilai memakai pencocokan manual karena batas permintaan AI.` : ''}`;
  } catch (error) {
    state.aiMessage = `Rekomendasi AI tidak tersedia: ${error.message} Pemetaan manual tetap dapat digunakan.`;
  } finally {
    state.aiMatching = false;
    renderGoogleSheetReview(false);
  }
}
async function commitGoogleSheetReview() { const state = googleSheetReview, summary = sheetReadySummary(), period = activePeriod; if (!state || !period || !summary.ready) return; const button = $('#google-sheet-commit'); if (!confirm(`Simpan ${summary.total} baris jadwal ke periode ${period.academic_year} · ${period.semester}? Jadwal yang memiliki histori akan ditolak API; roster mahasiswa tidak diubah.`)) return; button.disabled = true; button.textContent = 'Memvalidasi dan menyimpan…'; try { const jadwal = state.rows.map(row => { const selected = row._selected || {}; return { baris_sumber: Number(row.baris_sumber), prodi: row.prodi, hari: row.hari, jam: row.jam, kode_mk: row.kode_mk, mata_kuliah: row.mata_kuliah, dosen: row.dosen, frekuensi: row.frekuensi, kelas: row.kelas || '', laboratorium: row.laboratorium, asisten_1: row.asisten_1, asisten_2: row.asisten_2, asisten_3: row.asisten_3, peserta: row.peserta, sks: row.sks, id_user_dosen: selected.dosen ? Number(selected.dosen.id) : null, id_user_asisten_utama: selected.asisten_1 ? Number(selected.asisten_1.id) : null, id_user_asisten_pengajar_pendukung: selected.asisten_2 ? Number(selected.asisten_2.id) : null, id_user_pendamping_teknis: selected.asisten_3 ? Number(selected.asisten_3.id) : null } }); const result = await api('/admin/sync-google-sheet', { url: state.url, id_periode_akademik: Number(period.id), jadwal }, 'POST'); googleSheetReview = null; const differences = Array.isArray(result.baris_dengan_perbedaan_nama_mk_atau_sks) ? result.baris_dengan_perbedaan_nama_mk_atau_sks : [], assistantWarnings = Array.isArray(result.peringatan_ketersediaan_asisten) ? result.peringatan_ketersediaan_asisten : [], roleLabels = { ASISTEN_UTAMA: 'Asisten 1', ASISTEN_PENGAJAR_PENDUKUNG: 'Asisten 2', PENDAMPING_TEKNIS: 'Asisten Pendamping', ASISTEN_PENDAMPING: 'Asisten Pendamping' }, assistantWarningHtml = assistantWarnings.length ? `<div class="error gs-assistant-warning" role="status"><strong>Jadwal tersimpan dengan ${assistantWarnings.length} peringatan ketersediaan Asisten.</strong><ul>${assistantWarnings.map(item => `<li>Baris ${Number(item.baris_sumber)} · ${esc(roleLabels[item.kode_peran] || item.kode_peran || 'Asisten')} · ${esc(item.nama || `Akun ${Number(item.id_user) || ''}`)}: ${esc(item.alasan || (item.peringatan || []).map(warning => warning.pesan || '').filter(Boolean).join(' '))}</li>`).join('')}</ul><span>Bentrok tidak menghalangi sinkronisasi. Tinjau penugasan bila diperlukan.</span></div>` : ''; $('#google-sheet-review').innerHTML = `<section class="card gs-result"><div class="card-head"><h2>Sinkronisasi berhasil</h2><p>${Number(result.jumlah_baris)} baris diterapkan untuk ${esc(result.periode?.tahun_akademik || period.academic_year)} · ${esc(result.periode?.semester || period.semester)}.</p></div><div class="card-body stats">${[[result.frekuensi_dibuat, 'Frekuensi baru'], [result.nama_kelompok_diperbarui, 'Label frekuensi diperbarui'], [result.dibuat, 'Jadwal baru'], [result.diperbarui, 'Jadwal diperbarui'], [result.tidak_berubah, 'Tidak berubah'], [result.jumlah_peserta_sumber, 'Jumlah peserta sumber (informasi)']].map(([count, label]) => `<div class="stat"><span class="label">${esc(label)}</span><b>${Number(count || 0)}</b></div>`).join('')}</div>${assistantWarningHtml}${differences.length ? `<div class="warning">${differences.map(item => `Baris ${Number(item.baris)} · ${esc(item.frekuensi)}: ${(item.catatan || []).map(esc).join(' ')}`).join('<br>')}</div>` : ''}<div class="help">Roster mahasiswa tidak berubah. Frekuensi yang tidak tercantum pada Sheet tidak dihapus.</div></section>`; toast(assistantWarnings.length ? 'Jadwal tersimpan dengan peringatan ketersediaan Asisten.' : 'Jadwal berhasil divalidasi dan disimpan.') } catch (error) { if ($('#google-sheet-review')) renderGoogleSheetReview(false); const host = $('#google-sheet-review'); if (host) host.insertAdjacentHTML('afterbegin', `<div class="error gs-error" role="alert">${esc(error.message)}</div>`) } finally { const current = $('#google-sheet-commit'); if (current) { current.disabled = !sheetReadySummary().ready; current.textContent = `Simpan ${googleSheetReview?.rows.length || 0} baris ke API pusat` } } }
async function googleSheetScheduleSyncPage() {
  const view = $('#api-view'), storageKey = 'iclabs-praktikum-google-sheet-schedule-url', defaultUrl = 'https://docs.google.com/spreadsheets/d/1YtQ_cXtmvAZYeLkrbiSVtLq3wE9ZVvYEfNv3fIQrsiM/edit?gid=290740537'; let savedUrl = defaultUrl; try { savedUrl = localStorage.getItem(storageKey) || defaultUrl } catch { } const period = activePeriod;
  view.innerHTML = `<div class="intro"><div class="intro-head"><h1>Sinkron Jadwal dari Google Sheets</h1><p>Impor dan selaraskan jadwal praktikum dari Google Spreadsheet ke data kanonikal periode aktif.</p></div></div><section class="card"><div class="card-head"><h2>Sumber jadwal</h2><p>Pratinjau hanya membaca Sheet. Jadwal baru/perubahan belum disimpan sampai tombol simpan ditekan.</p></div><form id="google-sheet-sync-form" class="card-body"><label for="google-sheet-url">URL Google Spreadsheet</label><div class="row"><input id="google-sheet-url" name="url" type="url" value="${esc(savedUrl)}" required maxlength="2048" placeholder="https://docs.google.com/spreadsheets/d/..." style="flex:1;min-width:260px"><a class="small btn-action" href="#" target="_blank" rel="noopener noreferrer">${iconSvg('external-link')} <span>Buka Sheet</span></a></div><div class="form-grid" style="margin-top:18px"><div><small>PERIODE TUJUAN (AKTIF)</small><p><strong>${period ? `${esc(period.academic_year)} · ${esc(period.semester)}` : 'Belum ada periode aktif'}</strong></p><small>${period ? `${esc(period.start_date || '—')} – ${esc(period.end_date || '—')}` : 'Aktifkan periode terlebih dahulu.'}</small></div><div><small>METODE SINKRONISASI</small><p><strong>Perbarui dan tambahkan (upsert)</strong></p><small>Baris Sheet yang tidak ada tidak menghapus jadwal lama.</small></div></div><div class="warning gs-warning" style="margin-top:18px"><strong>Validasi dua tahap:</strong> kandidat yang paling mirip dipilih sebagai usulan awal dan ditandai oranye. Periksa setiap usulan lalu konfirmasi sebelum menyimpan; baris yang masih ambigu atau tidak cocok tidak dapat disimpan.</div><div class="help">Asisten 1 dan dosen wajib; Asisten 2 dan Asisten 3/Pendamping opsional. Dosen setelah tanda “/” diabaikan. Kolom Kelas (opsional) hanya menjadi label tampilan kelompok, misalnya TI_JARKOM-5 (B1); tidak membuat master kelas reguler. Jika kosong/tidak tersedia, nama tampilan mengikuti kode frekuensi. Jumlah Peserta hanya informasi, roster mahasiswa tidak berubah.</div><div id="google-sheet-sync-result" aria-live="polite"></div><div class="row" style="justify-content:flex-end;margin-top:18px"><button class="primary" id="google-sheet-preview-button" type="submit" ${period ? '' : 'disabled'}>${iconSvg('download')} <span>Ambil &amp; Pratinjau Sheet</span></button></div></form></section><div id="google-sheet-review"></div>`;
  view.querySelector('.gs-warning')?.insertAdjacentHTML('afterend', '<div class="help">Rekomendasi Asisten memakai jadwal kuliah pribadi dan penugasan Praktikum aktif. Kandidat berstatus tersedia ditampilkan lebih dahulu. Asisten 3/Pendamping hanya menampilkan jenjang ASISTEN_3. Bentrok ditandai sebagai peringatan dan tetap dapat disimpan.</div>');
  const input = $('#google-sheet-url'), link = view.querySelector('a'), updateSheetLink = () => { try { const url = new URL(input.value); link.href = url.protocol === 'https:' && url.hostname === 'docs.google.com' && url.pathname.startsWith('/spreadsheets/d/') ? url.href : '#' } catch { link.href = '#' } }; updateSheetLink(); input.oninput = updateSheetLink; input.addEventListener('input', () => { if (googleSheetReview && googleSheetReview.url !== input.value.trim()) { googleSheetReview = null; $('#google-sheet-review').innerHTML = ''; $('#google-sheet-sync-result').innerHTML = '<div class="warning">URL berubah. Ambil pratinjau baru agar tabel dan sumber Sheet tetap cocok.</div>' } });
  $('#google-sheet-sync-form').onsubmit = async event => { event.preventDefault(); if (!period) { toast('Belum ada periode akademik aktif.'); return } const button = $('#google-sheet-preview-button'), result = $('#google-sheet-sync-result'), review = $('#google-sheet-review'); button.disabled = true; button.innerHTML = `${iconSvg('refresh', 'spin')} <span>Mengambil Sheet…</span>`; result.innerHTML = '<div class="empty">Mengambil CSV publik. Database pusat belum diubah.</div>'; review.innerHTML = ''; googleSheetReview = null; try { const url = input.value.trim(), preview = await api('/admin/preview-google-sheet', { url }, 'POST'), references = await api('/references'); for (const key of ['program_studi', 'mata_kuliah', 'laboratorium', 'dosen', 'asisten']) if (!Array.isArray(references[key])) throw Error('Referensi master untuk pemetaan belum lengkap dari API pusat.'); for (const row of preview.jadwal || []) { row.dosen = String(row.dosen || '').split('/')[0].trim(); for (const field of ['asisten_1', 'asisten_2', 'asisten_3']) if (sheetEmptyOptional(row[field])) row[field] = '' } sheetInitializeMappings(preview.jadwal || [], references); googleSheetReview.url = url; googleSheetReview.checksum = preview.checksum_sha256; try { localStorage.setItem(storageKey, url) } catch { } result.innerHTML = `<div class="success gs-loaded" role="status">${Number(preview.jumlah_baris)} baris berhasil dibaca. Belum ada perubahan database. Cocokkan semua saran sebelum menyimpan.</div>`; renderGoogleSheetReview(false); requestGoogleSheetAssistantRecommendations() } catch (error) { googleSheetReview = null; result.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>` } finally { button.disabled = false; button.innerHTML = `${iconSvg('download')} <span>Ambil &amp; Pratinjau Sheet</span>` } };
  if (googleSheetReview && googleSheetReview.url === savedUrl) { $('#google-sheet-sync-result').innerHTML = '<div class="success gs-loaded" role="status">Pratinjau tersimpan sementara di halaman ini; belum ada perubahan database.</div>'; renderGoogleSheetReview(false) }
}
function apiPeriodSummary() { const p = activePeriod; return `<section class="period-summary" aria-label="Periode akademik pusat"><div><small>Periode aktif pusat</small><strong>${p ? esc(p.academic_year) + ' · ' + esc(p.semester) : 'Belum ada periode aktif'}</strong></div><div><small>Tanggal pelaksanaan</small><strong>${p ? esc(p.start_date || '—') + ' – ' + esc(p.end_date || '—') : '—'}</strong></div></section>` }
async function apiSyncProduction(button, payload, onSuccess) {
  const originalHtml = button.innerHTML;
  button.disabled = true;
  button.innerHTML = `${iconSvg('refresh', 'spin')} <span>Menyinkronkan…</span>`;
  try {
    const result = await api('/sync-production', payload, 'POST');
    const presensiCount = Number(result.presensi_dibuat || 0) + Number(result.presensi_diperbarui || 0);
    const nilaiCount = Number(result.nilai_dibuat || 0) + Number(result.nilai_diperbarui || 0);
    const sesiCount = Number(result.sesi_dibuat || 0) + Number(result.sesi_diperbarui || 0);
    toast(`Sinkronisasi berhasil! ${sesiCount} sesi, ${presensiCount} presensi, ${nilaiCount} nilai diselaraskan.`);
    if (typeof onSuccess === 'function') {
      await onSuccess(result);
    }
  } catch (error) {
    toast(error.message || 'Gagal menyinkronkan data dari produksi.');
  } finally {
    button.disabled = false;
    button.innerHTML = originalHtml;
  }
}

async function apiDashboard() {
  const view = $('#api-view'); view.innerHTML = '<div class="empty">Memuat ringkasan dari API pusat…</div>';
  try {
    const d = await api('/dashboard'), attendanceOnly = user.role === 'dosen', percent = value => value === null ? '—' : `${Number(value).toFixed(1)}%`, clampPct = value => Math.max(0, Math.min(100, Number(value) || 0));
    const cards = attendanceOnly ? [
      [d.jumlah_kelas, 'Kelas yang diampu', 'grid', 'Periode akademik aktif'],
      [d.jumlah_peserta_aktif, 'Mahasiswa', 'users', 'Peserta aktif di kelas'],
      [d.pertemuan_tercatat, 'Pertemuan tercatat', 'calendar', `${Number(d.pertemuan_direncanakan || 0)} direncanakan`],
      [d.presensi_terisi, 'Presensi tercatat', 'shield-check', `${Number(d.presensi_diharapkan || 0)} target (${percent(d.persentase_presensi)})`]
    ] : [
      [d.jumlah_kelas, 'Kelas Praktikum', 'grid', 'Akses kelas aktif'],
      [d.jumlah_peserta_aktif, 'Peserta aktif', 'users', 'Mahasiswa terdaftar'],
      [d.pertemuan_tercatat, 'Pertemuan tercatat', 'calendar', `${Number(d.pertemuan_direncanakan || 0)} direncanakan`],
      [d.jadwal_perlu_perhatian, 'Perlu perhatian', 'shield-check', 'Diajukan atau revisi']
    ];
    const progressRowHtml = (label, filled, target, pctVal) => `<div class="api-progress-item"><div class="api-progress-row"><span>${label}</span><strong>${Number(filled || 0)} / ${Number(target || 0)}</strong><span class="api-progress-pct">${percent(pctVal)}</span></div><div class="api-progress-track" aria-hidden="true"><span class="api-progress-fill" style="width:${clampPct(pctVal)}%"></span></div></div>`;
    const progress = attendanceOnly
      ? progressRowHtml('Presensi pertemuan', d.presensi_terisi, d.presensi_diharapkan, d.persentase_presensi)
      : `${progressRowHtml('Presensi pertemuan', d.presensi_terisi, d.presensi_diharapkan, d.persentase_presensi)}${progressRowHtml('Nilai komponen praktikum', d.nilai_terisi, d.nilai_diharapkan, d.persentase_nilai)}`;
    const syncButtonHtml = ['admin', 'asisten'].includes(user.role)
      ? `<button class="small btn-sync-action" id="api-sync-production" type="button" title="Ambil data presensi dan nilai dari produksi">${iconSvg('refresh')} <span>${user.role === 'admin' ? 'Sinkronkan Semua Data Produksi' : 'Sinkronkan Kelas Saya'}</span></button>`
      : '';
    const recentClasses = Array.isArray(classes) ? classes.slice(0, 5) : [];
    const recentClassesHtml = recentClasses.length ? `<section class="card"><div class="card-head row between"><div><h2>${attendanceOnly ? 'Kelas yang Diampu' : 'Daftar Kelas Periode Aktif'}</h2><p>Menampilkan ${recentClasses.length} dari ${classes.length} frekuensi praktikum pada periode berjalan.</p></div><button type="button" class="small btn-action" id="api-open-all-classes"><span>Lihat semua (${classes.length})</span> ${iconSvg('arrow-right')}</button></div><div class="table-wrap"><table class="api-class-table"><thead><tr><th>Mata kuliah</th><th>Program studi</th><th>Kelompok / Kode</th><th>Status</th><th class="num">Pertemuan</th><th class="col-actions">Aksi</th></tr></thead><tbody>${recentClasses.map(c => `<tr><td><strong>${esc(c.mata_kuliah?.kode || '')} · ${esc(c.mata_kuliah?.nama || '')}</strong></td><td>${esc(c.program_studi?.nama || '')}</td><td><strong>${esc(c.kelompok?.nama || c.kelompok?.kode || '')}</strong><small class="muted" style="display:block">Kode: ${esc(c.kelompok?.kode || '')}</small></td><td><div class="row" style="gap:4px;flex-wrap:wrap">${apiClassStatusBadge(c.status)}${apiRoleBadge(c.peran_di_kelas)}</div></td><td class="num">${Number(c.jumlah_pertemuan_rencana || 0)}</td><td class="col-actions"><button type="button" class="small btn-table-action" data-api-class="${Number(c.id_kelas_kuliah)}"><span>${attendanceOnly ? 'Lihat aktivitas' : 'Buka'}</span> ${iconSvg('arrow-right')}</button></td></tr>`).join('')}</tbody></table></div></section>` : '';
    view.innerHTML = `<div class="intro row between"><div class="intro-head"><h1>${attendanceOnly ? 'Ringkasan Dosen' : 'Ringkasan Praktikum'}</h1><p>Pantau aktivitas kelas, pencatatan presensi pertemuan, dan progres penilaian periode berjalan.</p></div><div class="row">${syncButtonHtml}<button class="primary small" id="api-open-classes"><span>${attendanceOnly ? 'Lihat kelas dan hasil' : 'Buka daftar kelas'}</span> ${iconSvg('arrow-right')}</button></div></div><div class="stats">${cards.map(([n, label, , note]) => `<div class="stat"><span class="label">${esc(label)}</span><b>${esc(n ?? '—')}</b><small>${esc(note)}</small></div>`).join('')}</div><section class="card"><div class="card-head row between"><div><h2>${attendanceOnly ? 'Rekap Kehadiran' : 'Progres Pengisian'}</h2><p>Proporsi pencatatan aktual terhadap target pertemuan dan komponen penilaian pada periode aktif.</p></div></div><div class="card-body api-progress-list">${progress}</div></section>${recentClassesHtml}`;
    const openClassList = () => { page = user.role === 'dosen' ? 'Aktivitas & Presensi' : 'Kelas Praktikum'; apiCurrent = null; renderApiMode() };
    $('#api-open-classes').onclick = openClassList;
    if ($('#api-open-all-classes')) $('#api-open-all-classes').onclick = openClassList;
    if ($('#api-sync-production')) {
      $('#api-sync-production').onclick = async () => {
        const isAll = user.role === 'admin';
        if (!confirm(isAll ? 'Mulai sinkronisasi seluruh data presensi dan nilai dari database produksi legacy?' : 'Mulai sinkronisasi data presensi dan nilai untuk seluruh kelas yang Anda ampu dari database produksi?')) return;
        await apiSyncProduction($('#api-sync-production'), {}, async () => {
          await apiDashboard();
        });
      };
    }
  } catch (error) { view.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>` }
}
let apiCurrent = null;
let selectedAcademicPeriod = '';
async function apiClassListPage() {
  const view = $('#api-view');
  view.innerHTML = '<div class="empty">Memuat kelas dari API pusat…</div>';
  try {
    const isLecturer = user.role === 'dosen', lecturerView = page === 'Hasil Penilaian' ? 'hasil' : 'aktivitas';
    let periods = [];
    if (user.role === 'admin') {
      const management = await api('/admin/periods');
      periods = management.periode || [];
    }
    const queryParams = new URLSearchParams();
    if (selectedAcademicPeriod) queryParams.set('id_periode_akademik', selectedAcademicPeriod);
    if (isLecturer) queryParams.set('dosen_view', lecturerView);
    const query = queryParams.toString() ? `?${queryParams}` : '';
    classes = await api('/classes' + query);
    const isCurrent = !selectedAcademicPeriod || Number(selectedAcademicPeriod) === Number(activePeriod?.id);
    const canChangeStatus = user.role === 'admin' && isCurrent;
    const statusSelectionControls = canChangeStatus ? `<div class="api-class-status-toolbar">
      <div class="row between api-class-status-toolbar-main">
        <div class="row">
          <button type="button" class="small" data-select-class-status="DRAF">${iconSvg('check-square')} <span>Pilih semua Draf</span></button>
          <button type="button" class="small" data-select-class-status="AKTIF">${iconSvg('check-square')} <span>Pilih semua Aktif</span></button>
          <button type="button" class="small" id="api-clear-class-selection">${iconSvg('close')} <span>Batalkan pilihan</span></button>
          <span class="help" id="api-class-selection-count">Belum ada frekuensi dipilih.</span>
        </div>
        <div class="row">
          <button type="button" class="primary small" id="api-activate-classes" disabled>${iconSvg('check-circle')} <span>Aktifkan terpilih</span></button>
          <button type="button" class="small" id="api-redraft-classes" disabled>${iconSvg('rotate-ccw')} <span>Kembalikan ke Draf</span></button>
        </div>
      </div>
      <p class="help">Aktivasi perlu jadwal aktif, Dosen Pengampu, dan Asisten Utama. Kelas hanya dapat dikembalikan ke Draf sebelum ada sesi, pengajuan workflow, atau nilai. Mengubah filter akan mengosongkan pilihan massal.</p>
    </div>`: '';
    const classStatuses = [...new Set(classes.map(c => String(c.status || '')).filter(Boolean))];
    const classFilters = user.role === 'admin' ? `<div class="api-class-filters"><label>Status<select id="api-class-status-filter"><option value="">Semua status</option>${classStatuses.map(status => `<option value="${esc(status)}">${esc(apiStatus(status))}</option>`).join('')}</select></label><label class="api-class-search">Cari kelas<input id="api-class-search" type="search" placeholder="Mata kuliah, prodi, atau frekuensi"></label><span id="api-class-filter-count" class="help">${classes.length} kelas</span></div>` : '';
    const pageTitle = isLecturer ? (lecturerView === 'hasil' ? 'Hasil Penilaian' : 'Aktivitas & Presensi') : 'Kelas Praktikum';
    const pageDescription = isLecturer ? (lecturerView === 'hasil' ? 'Jadwal yang sudah diajukan Asisten. Tinjau nilai dan beri revisi atau verifikasi.' : 'Semua kelas dan jadwal yang diampu, termasuk yang belum diajukan. Lihat rekap presensi per pertemuan.') : 'Daftar mengikuti batch akademik yang dipilih; histori lama tetap tersimpan dan tidak menerima perubahan.';
    const emptyMessage = isLecturer ? (lecturerView === 'hasil' ? 'Belum ada hasil penilaian yang diajukan kepada Anda.' : 'Belum ada kelas yang ditugaskan kepada akun Dosen ini.') : 'Belum ada kelas pada periode ini.';
    view.innerHTML = `<div class="intro"><div class="intro-head"><h1>${pageTitle}</h1><p>${esc(pageDescription)}</p></div></div><section class="card"><div class="card-head row between"><div><h2>${selectedAcademicPeriod ? 'Histori Kelas' : (isLecturer ? (lecturerView === 'hasil' ? 'Pengajuan untuk Ditinjau' : 'Jadwal yang Diampu') : 'Kelas Periode Aktif')}</h2><p id="api-class-count">${classes.length} kelas ditampilkan.</p></div><div class="row card-head-actions">${user.role === 'admin' ? `<label class="inline-field">Periode<select id="api-period-filter"><option value="">Periode aktif (${esc(activePeriod?.academic_year || 'belum ditentukan')})</option>${periods.filter(period => Number(period.id_periode_akademik) !== Number(activePeriod?.id)).map(period => `<option value="${Number(period.id_periode_akademik)}" ${String(period.id_periode_akademik) === selectedAcademicPeriod ? 'selected' : ''}>${esc(period.tahun_akademik)} · ${esc(period.semester.nama)}${period.aktif ? ' · aktif' : ''}</option>`).join('')}</select></label><button class="small btn-subtle-teal" id="api-period-recap-download" title="Unduh rekap nilai & presensi periode dalam format Excel multi-sheet">${iconSvg('download')} <span>Unduh rekap periode</span></button>${isCurrent ? `<button class="small" id="api-import-schedule">${iconSvg('upload')} <span>Import jadwal</span></button><button class="small" id="api-roster-download">${iconSvg('download')} <span>Unduh roster rotasi</span></button><button class="small" id="api-roster-import">${iconSvg('upload')} <span>Impor roster rotasi</span></button><button class="primary small" id="api-create-class">${iconSvg('plus')} <span>Tambah kelas</span></button>` : ''}` : ''}</div></div>${selectedAcademicPeriod && !isCurrent ? '<div class="warning">Mode histori: data periode ini hanya untuk ditinjau. Kelas baru dan import jadwal hanya tersedia pada periode aktif.</div>' : ''}${classFilters}${statusSelectionControls}<div class="table-wrap"><table class="api-class-table"><thead><tr>${canChangeStatus ? '<th class="col-check">Pilih</th>' : ''}<th>Mata kuliah</th><th>Program studi</th><th>Kelompok / Kode</th><th>Periode</th><th>Status</th><th class="num">Pertemuan</th><th class="col-actions">Aksi</th></tr></thead><tbody>${classes.map(c => `<tr data-admin-class-row data-status="${esc(c.status)}" data-search="${esc([c.mata_kuliah.kode, c.mata_kuliah.nama, c.program_studi.kode, c.program_studi.nama, c.kelompok.nama, c.kelompok.kode].join(' ').toLowerCase())}">${canChangeStatus ? `<td class="col-check">${['DRAF', 'AKTIF'].includes(c.status) ? `<input type="checkbox" data-class-status-select value="${Number(c.id_kelas_kuliah)}" data-status="${esc(c.status)}" data-version="${Number(c.kelompok.versi)}" aria-label="Pilih ${esc(c.mata_kuliah.kode)} ${esc(c.kelompok.nama || c.kelompok.kode)}">` : '—'}</td>` : ''}<td><strong>${esc(c.mata_kuliah.kode)} · ${esc(c.mata_kuliah.nama)}</strong></td><td>${esc(c.program_studi.nama)}</td><td><strong>${esc(c.kelompok.nama || c.kelompok.kode)}</strong><small class="muted" style="display:block">Kode: ${esc(c.kelompok.kode)}</small></td><td>${esc(c.periode.tahun_akademik)} · ${esc(c.periode.semester)}</td><td><div class="row" style="gap:4px;flex-wrap:wrap">${apiClassStatusBadge(c.status)}${apiRoleBadge(c.peran_di_kelas)}</div></td><td class="num">${Number(c.jumlah_pertemuan_rencana)}</td><td class="col-actions"><button type="button" class="small btn-table-action" data-api-class="${Number(c.id_kelas_kuliah)}"><span>${isLecturer ? (lecturerView === 'hasil' ? 'Tinjau' : 'Lihat aktivitas') : 'Buka'}</span> ${iconSvg('arrow-right')}</button></td></tr>`).join('') || `<tr><td colspan="${canChangeStatus ? 8 : 7}" class="empty">${emptyMessage}</td></tr>`}${user.role === 'admin' && classes.length ? `<tr id="api-class-filter-empty" hidden><td colspan="${canChangeStatus ? 8 : 7}" class="empty">Tidak ada kelas yang cocok dengan filter.</td></tr>` : ''}</tbody></table></div></section>`;
    const applyClassFilters = () => {
      const status = $('#api-class-status-filter')?.value || '', query = ($('#api-class-search')?.value || '').trim().toLowerCase();
      let visible = 0;
      view.querySelectorAll('[data-admin-class-row]').forEach(row => { const matches = (!status || row.dataset.status === status) && (!query || row.dataset.search.includes(query)); row.hidden = !matches; if (matches) visible++ });
      if ($('#api-class-filter-empty')) $('#api-class-filter-empty').hidden = visible > 0;
      if ($('#api-class-count')) $('#api-class-count').textContent = `${visible} dari ${classes.length} kelas ditampilkan.`;
      if ($('#api-class-filter-count')) $('#api-class-filter-count').textContent = `${visible} kelas`;
      if (canChangeStatus) { view.querySelectorAll('[data-class-status-select]').forEach(input => { input.checked = false }); $('#api-class-selection-count').textContent = 'Belum ada frekuensi dipilih.'; $('#api-activate-classes').disabled = true; $('#api-redraft-classes').disabled = true }
    };
    if ($('#api-class-status-filter')) $('#api-class-status-filter').onchange = applyClassFilters;
    if ($('#api-class-search')) $('#api-class-search').oninput = applyClassFilters;
    if ($('#api-period-filter')) $('#api-period-filter').onchange = async event => { selectedAcademicPeriod = event.target.value; await apiClassListPage() };
    if ($('#api-period-recap-download')) $('#api-period-recap-download').onclick = () => { const pId = Number(selectedAcademicPeriod || activePeriod?.id); if (!pId) return toast('Pilih periode terlebih dahulu.'); const period = periods.find(item => Number(item.id_periode_akademik) === pId); const label = period ? `${period.tahun_akademik}-${period.semester?.nama || ''}` : pId; downloadPeriodRecap(pId, label); };
    if ($('#api-create-class')) $('#api-create-class').onclick = () => apiClassForm();
    if ($('#api-import-schedule')) $('#api-import-schedule').onclick = importSchedule;
    if ($('#api-roster-download')) $('#api-roster-download').onclick = () => { window.location.href = apiUrl('/admin/roster-rotation/template') };
    if ($('#api-roster-import')) $('#api-roster-import').onclick = apiRosterRotationImport;
    document.querySelectorAll('[data-api-class]').forEach(button => button.onclick = () => isCurrent ? apiClassDetailWithEntry(Number(button.dataset.apiClass), undefined, undefined, isLecturer ? (lecturerView === 'hasil' ? 'grading' : 'attendance') : undefined) : apiClassDetail(Number(button.dataset.apiClass)));
    if (canChangeStatus) {
      const selected = () => [...view.querySelectorAll('[data-class-status-select]:checked')].filter(input => !input.closest('tr')?.hidden);
      const updateSelectionActions = () => {
        const rows = selected();
        const sameStatus = rows.length > 0 && rows.every(row => row.dataset.status === rows[0].dataset.status);
        $('#api-class-selection-count').textContent = rows.length ? `${rows.length} frekuensi dipilih.` : 'Belum ada frekuensi dipilih.';
        $('#api-activate-classes').disabled = !sameStatus || rows[0]?.dataset.status !== 'DRAF';
        $('#api-redraft-classes').disabled = !sameStatus || rows[0]?.dataset.status !== 'AKTIF';
      };
      view.querySelectorAll('[data-class-status-select]').forEach(input => input.onchange = updateSelectionActions);
      view.querySelectorAll('[data-select-class-status]').forEach(button => button.onclick = () => {
        const status = button.dataset.selectClassStatus;
        view.querySelectorAll('[data-class-status-select]').forEach(input => { input.checked = !input.closest('tr')?.hidden && input.dataset.status === status });
        updateSelectionActions();
      });
      $('#api-clear-class-selection').onclick = () => { view.querySelectorAll('[data-class-status-select]').forEach(input => { input.checked = false }); updateSelectionActions() };
      $('#api-activate-classes').onclick = () => changeSelectedClassStatuses('AKTIF', selected());
      $('#api-redraft-classes').onclick = () => changeSelectedClassStatuses('DRAF', selected());
    }
  } catch (error) { view.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>` }
}
async function changeSelectedClassStatuses(targetStatus, selectedRows) {
  const rows = selectedRows.map(row => ({
    id_kelas_kuliah: Number(row.value),
    versi: Number(row.dataset.version),
    kode_frekuensi: classes.find(item => Number(item.id_kelas_kuliah) === Number(row.value))?.kelompok.kode || row.value,
  }));
  if (!rows.length) return;
  const activating = targetStatus === 'AKTIF';
  const action = activating ? 'diaktifkan' : 'dikembalikan ke Draf';
  const modal = dialog(`<form><h2>${activating ? 'Aktifkan' : 'Kembalikan ke Draf'} ${rows.length} frekuensi</h2><p>${activating ? 'Kelas akan dapat digunakan untuk proses praktikum dan pengajuan hasil ke Dosen.' : 'Kelas tetap menyimpan jadwal dan roster, tetapi pengajuan workflow dihentikan sementara.'}</p><div class="table-wrap"><table><thead><tr><th>FREKUENSI</th><th>MATA KULIAH</th><th>STATUS TUJUAN</th></tr></thead><tbody>${rows.map(row => { const item = classes.find(value => Number(value.id_kelas_kuliah) === row.id_kelas_kuliah); return `<tr><td>${esc(row.kode_frekuensi)}</td><td>${esc(item?.mata_kuliah.kode || '')}</td><td>${activating ? 'Aktif' : 'Draf'}</td></tr>` }).join('')}</tbody></table></div><label>Alasan / catatan audit (wajib, minimal 10 karakter)<textarea name="alasan" minlength="10" maxlength="500" required placeholder="Contoh: jadwal dan penugasan sudah ditinjau"></textarea></label><p class="help">Seluruh pilihan diproses atomik. Jika satu frekuensi bermasalah, tidak ada yang berubah.</p><div id="api-class-status-error" aria-live="polite"></div><div class="row"><button type="button" data-close>Batal</button><button class="primary" type="submit">${iconSvg('check-circle')} <span>Konfirmasi ${activating ? 'aktivasi' : 'pengembalian ke Draf'}</span></button></div></form>`);
  modal.querySelector('form').onsubmit = async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('[type="submit"]');
    button.disabled = true;
    button.textContent = 'Memeriksa dan menyimpan…';
    try {
      const alasan = new FormData(event.currentTarget).get('alasan');
      const result = await api('/admin/classes/status', { kelas: rows.map(({ id_kelas_kuliah, versi }) => ({ id_kelas_kuliah, versi })), status_tujuan: targetStatus, alasan }, 'PATCH');
      modal.close();
      toast(`${Number(result.jumlah_diubah)} frekuensi berhasil ${action}.`);
      await apiClassListPage();
    } catch (error) {
      $('#api-class-status-error').innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>`;
      button.disabled = false;
      button.innerHTML = `${iconSvg('check-circle')} <span>Konfirmasi ${activating ? 'aktivasi' : 'pengembalian ke Draf'}</span>`;
    }
  };
}
function apiStatus(value) { return String(value || '—').replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, character => character.toUpperCase()) }
function apiClassStatusBadge(status) { const code = String(status || '').toUpperCase(), tone = { DRAF: 'yellow', AKTIF: 'green', DIBATALKAN: 'red', SELESAI: 'blue' }[code] || ''; return `<span class="badge ${tone}">${esc(apiStatus(code))}</span>` }
function apiRoleBadge(role) {
  if (!role) return '';
  const map = {
    ADMIN: { label: 'Admin', tone: 'blue' },
    DOSEN: { label: 'Dosen', tone: 'blue' },
    ASISTEN: { label: 'Asisten', tone: 'green' },
    ASISTEN_3: { label: 'Asisten 3 (Pendamping)', tone: 'yellow' },
    PRAKTIKAN: { label: 'Praktikan', tone: '' }
  };
  const item = map[role] || { label: apiStatus(role), tone: '' };
  return `<span class="badge ${item.tone}" title="Peran Anda pada kelas ini">${esc(item.label)}</span>`;
}
async function apiClassList() { if (user?.role === 'admin') return apiClassListPage(); const view = $('#api-view'); view.innerHTML = '<div class="empty">Memuat kelas dari API pusat…</div>'; try { const result = await api('/classes'); if (!Array.isArray(result)) throw Error('Format daftar kelas tidak valid.'); classes = result; view.innerHTML = `<div class="intro"><div class="intro-head"><h1>Kelas Praktikum</h1><p>Data kelas dan kelompok dibaca dari database integrasi ICLabs.</p></div></div><section class="card"><div class="card-head row between"><div><h2>Daftar kelas</h2><p>${classes.length} kelas dapat diakses oleh akun Anda.</p></div>${user.role === 'admin' ? `<div class="row"><button class="small" id="api-import-schedule">${iconSvg('upload')} <span>Import jadwal</span></button><button class="primary" id="api-create-class">${iconSvg('plus')} <span>Tambah kelas</span></button></div>` : ''}</div>${classes.map(c => `<button class="api-class-row" data-api-class="${Number(c.id_kelas_kuliah)}"><span><strong>${esc(c.mata_kuliah.kode)} · ${esc(c.mata_kuliah.nama)}</strong><small>${esc(c.program_studi.nama)} · Kelompok ${esc(c.kelompok.nama || c.kelompok.kode)} · Kode ${esc(c.kelompok.kode)} · ${esc(c.periode.tahun_akademik)} ${esc(c.periode.semester)}</small></span><span class="badge">${esc(apiStatus(c.status))}</span>${apiRoleBadge(c.peran_di_kelas)}<span class="badge">${Number(c.jumlah_pertemuan_rencana)} pertemuan</span><span class="btn-row-arrow" aria-hidden="true">${iconSvg('arrow-right')}</span></button>`).join('') || (user.role === 'dosen' ? '<div class="empty">Belum ada kelas Praktikum yang ditugaskan ke akun Anda.</div>' : '<div class="empty">Belum ada kelas Praktikum yang dapat diakses.</div>')}</section>`; if (user.role === 'admin') { $('#api-create-class').onclick = () => apiClassForm(); $('#api-import-schedule').onclick = importSchedule } document.querySelectorAll('[data-api-class]').forEach(b => b.onclick = () => apiClassDetail(Number(b.dataset.apiClass))) } catch (e) { view.innerHTML = `<div class="error" role="alert">${esc(e.message)}</div>` } }
async function apiClassDetail(id) {
  const view = $('#api-view'); view.innerHTML = '<div class="empty">Memuat detail kelas…</div>';
  try {
    const c = await api('/classes/' + id); apiCurrent = id;
    const schedules = c.jadwal || [], sessions = c.sesi || [], components = c.komponen_nilai || [], students = c.peserta || [];
    const weekday = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    const regSched = schedules.find(s => s.pola_perulangan === 'MINGGUAN') || schedules[0] || null;
    const regSchedLabel = regSched ? `${weekday[Number(regSched.hari_ke)] || 'Hari'}, ${String(regSched.jam_mulai || '').slice(0, 5)}–${String(regSched.jam_selesai || '').slice(0, 5)}` : 'Belum terjadwal';
    const regLabLabel = regSched ? (regSched.nama_laboratorium || regSched.nama_ruang || regSched.kode_ruang || 'Ruang belum diatur') : '—';
    const readTabs = [
      ['schedules', 'Jadwal & Penugasan', 'calendar', schedules.length],
      ['sessions', 'Sesi Praktikum', 'clock', sessions.length],
      ['components', 'Komponen Penilaian', 'layers', components.length],
      ['roster', Object.prototype.hasOwnProperty.call(c, 'peserta') ? 'Peserta Aktif' : 'Ringkasan Anda', 'users', Object.prototype.hasOwnProperty.call(c, 'peserta') ? students.length : null]
    ];
    view.innerHTML = `
      <section class="card freq-header-card">
        <div class="freq-topbar row between">
          <div class="row" style="gap:8px">
            <button type="button" class="small btn-back" id="api-back">${iconSvg('arrow-left')} <span>Kembali ke kelas</span></button>
            ${user.role === 'admin' && Number(c.periode.id) === Number(activePeriod?.id) && c.status === 'DRAF' ? `<button type="button" class="small danger" id="api-delete-draft">${iconSvg('trash')} <span>Hapus kelas draft</span></button>` : ''}
          </div>
          <div class="row" style="gap:6px">
            ${apiClassStatusBadge(c.status)}
            ${apiRoleBadge(c.peran_di_kelas)}
          </div>
        </div>
        <div class="freq-hero-body">
          <div class="freq-title-row">
            <span class="badge blue">${esc(c.mata_kuliah.kode)}</span>
            <h1>${esc(c.mata_kuliah.nama)}</h1>
          </div>
          <div class="freq-meta-strip">
            <div class="freq-meta-item">
              <div>
                <span class="meta-label">${iconSvg('layers')} Kelompok / Kode Frekuensi</span>
                <strong>${esc(c.kelompok.nama || c.kelompok.kode)} · ${esc(c.kelompok.kode)}</strong>
              </div>
            </div>
            <div class="freq-meta-item">
              <div>
                <span class="meta-label">${iconSvg('calendar')} Program Studi &amp; Periode</span>
                <strong>${esc(c.program_studi.nama)} · ${esc(c.periode.tahun_akademik)} ${esc(c.periode.semester)}</strong>
              </div>
            </div>
            <div class="freq-meta-item">
              <div>
                <span class="meta-label">${iconSvg('clock')} Jadwal &amp; Laboratorium</span>
                <strong>${esc(regSchedLabel)} · ${esc(regLabLabel)}</strong>
              </div>
            </div>
            <div class="freq-meta-item">
              <div>
                <span class="meta-label">${iconSvg('users')} Target &amp; Peserta</span>
                <strong>${Number(c.jumlah_pertemuan_rencana)} pertemuan · ${students.length} mahasiswa</strong>
              </div>
            </div>
          </div>
        </div>
        ${c.catatan ? `<div class="warning" style="margin:0;border-width:1px 0 0;border-radius:0">${esc(c.catatan)}</div>` : ''}
      </section>
      <div class="api-detail-tabs" role="tablist" aria-label="Bagian detail kelas">
        ${readTabs.map(([key, label, ic, cnt], idx) => `<button type="button" role="tab" aria-selected="${idx === 0}" data-read-tab="${key}" class="${idx === 0 ? 'active' : ''}">${iconSvg(ic)} <span>${label}</span>${cnt !== null && cnt !== undefined ? `<span class="tab-count">${cnt}</span>` : ''}</button>`).join('')}
      </div>
      <section class="card api-detail-panel" data-read-panel="schedules">
        <div class="card-head"><h2>Jadwal dan penugasan</h2><p>Rincian waktu pelaksanaan, laboratorium, dan tim pengajar frekuensi ini.</p></div>
        <div class="schedule-cards-list">
          ${schedules.length ? schedules.map(s => `
            <div class="api-detail-row schedule-card">
              <div class="schedule-card-top row between">
                <div class="row" style="gap:8px">
                  <span class="schedule-time-pill">${iconSvg('clock')} <strong>${esc(weekday[Number(s.hari_ke)] || 'Hari belum ditentukan')} · ${esc(String(s.jam_mulai || '').slice(0, 5))}–${esc(String(s.jam_selesai || '').slice(0, 5))}</strong></span>
                  <span class="badge">${esc(apiStatus(s.pola_perulangan))}</span>
                  ${apiClassStatusBadge(s.status)}
                </div>
                <span class="badge blue">Workflow: ${esc(apiStatus(s.status_workflow))}</span>
              </div>
              <div class="schedule-card-grid">
                <div class="schedule-meta-box">
                  <small>${iconSvg('map-pin')} Ruangan &amp; Rentang Tanggal</small>
                  <strong>${esc(s.nama_laboratorium || s.nama_ruang || s.kode_ruang || 'Ruang belum ditentukan')}</strong>
                  <span>${esc(s.tanggal_mulai || '—')} – ${esc(s.tanggal_selesai || '—')}</span>
                </div>
                <div class="schedule-meta-box" style="grid-column: span 2">
                  <small>${iconSvg('users')} Tim Pengajar &amp; Asisten</small>
                  <div class="schedule-staff-chips">
                    ${(s.pengajar || []).map(p => `<span class="staff-chip"><small>${esc(p.nama_peran || p.peran)}:</small> <strong>${esc(p.nama || '—')}</strong></span>`).join('') || '<span class="muted">Belum ada penugasan</span>'}
                  </div>
                </div>
              </div>
            </div>
          `).join('') : '<div class="empty">Jadwal praktikum belum tersedia.</div>'}
        </div>
      </section>
      <section class="card api-detail-panel" data-read-panel="sessions" hidden>
        <div class="card-head"><h2>Sesi praktikum</h2></div>
        ${sessions.length ? `<div class="table-wrap"><table><thead><tr><th class="num">Pertemuan</th><th>Tanggal</th><th>Topik / Materi</th><th>Status</th></tr></thead><tbody>${sessions.map(s => `<tr><td class="num">${Number(s.nomor_pertemuan)}</td><td>${esc(s.tanggal_sesi || '—')}</td><td>${esc([s.topik, s.materi].filter(Boolean).join(' · ') || '—')}</td><td>${esc(apiStatus(s.status))}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada sesi yang tercatat.</div>'}
      </section>
      <section class="card api-detail-panel" data-read-panel="components" hidden>
        <div class="card-head"><h2>Komponen penilaian</h2></div>
        ${components.length ? `<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th class="num">Bobot</th><th>Status</th></tr></thead><tbody>${components.map(x => `<tr><td>${esc(x.kode)}</td><td>${esc(x.nama)}</td><td class="num">${Number(x.bobot_persen)}%</td><td>${x.aktif ? 'Aktif' : 'Nonaktif'}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Komponen nilai belum disiapkan.</div>'}
      </section>
      <section class="card api-detail-panel" data-read-panel="roster" hidden>
        ${Object.prototype.hasOwnProperty.call(c, 'peserta') ? `<div class="card-head"><h2>Peserta aktif · ${students.length}</h2></div>${students.length ? `<div class="table-wrap"><table><thead><tr><th>NIM</th><th>Nama</th><th class="num">Kehadiran tercatat</th><th class="num">Nilai tercatat</th></tr></thead><tbody>${students.map(s => `<tr><td>${esc(s.nim)}</td><td>${esc(s.nama)}</td><td class="num">${(s.kehadiran || []).length}</td><td class="num">${(s.nilai || []).length}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada peserta aktif.</div>'}` : `<div class="card-head"><h2>Ringkasan Anda</h2></div><div class="card-body"><p>Kehadiran tercatat: ${(c.kehadiran_saya || []).length} · Komponen nilai tercatat: ${(c.nilai_saya || []).length}</p><p>Detail nilai hanya memuat data akun Anda.</p></div>`}
      </section>
    `;
    view.querySelectorAll('[data-read-tab]').forEach(btn => btn.onclick = () => {
      view.querySelectorAll('[data-read-tab]').forEach(b => { const active = b === btn; b.classList.toggle('active', active); b.setAttribute('aria-selected', String(active)); });
      view.querySelectorAll('[data-read-panel]').forEach(p => { p.hidden = p.dataset.readPanel !== btn.dataset.readTab; });
    });
    $('#api-back').onclick = () => { apiCurrent = null; apiClassList() };
    if ($('#api-delete-draft')) $('#api-delete-draft').onclick = () => apiDeleteDraftClass(c);
  } catch (e) {
    apiCurrent = null;
    view.innerHTML = `<div class="error" role="alert">${esc(e.message)}</div><button class="small btn-back" id="api-back">${iconSvg('arrow-left')} <span>Kembali</span></button>`;
    $('#api-back').onclick = apiClassList;
  }
}
async function apiStudentGrades() {
  const view = $('#api-view');
  view.innerHTML = '<div class="empty">Memuat data praktikum dari API pusat…</div>';
  try {
    const [rows, meetingResult] = await Promise.all([
      api('/student/grades'),
      api('/meeting-grades').then(items => ({ items, error: null })).catch(error => ({ items: [], error: error.message }))
    ]);
    if (!Array.isArray(rows)) throw Error('Format nilai mahasiswa tidak valid.');
    const groups = new Map(), meetingGroups = new Map();
    for (const row of rows) {
      const key = String(row.id_kelas_kuliah);
      if (!groups.has(key)) {
        groups.set(key, {
          first: row,
          summary: row.ringkasan_nilai,
          attendance: row.kehadiran || [],
          attendanceSummary: row.ringkasan_kehadiran || { total_hadir: 0, total_sesi: 0, persentase: 0 },
          components: []
        });
      }
      if (row.kode_komponen) groups.get(key).components.push(row);
    }
    for (const meeting of meetingResult.items) {
      const key = String(meeting.id_kelas_kuliah);
      if (!meetingGroups.has(key)) meetingGroups.set(key, { first: meeting, meetings: [] });
      meetingGroups.get(key).meetings.push(meeting);
    }
    const fmt = value => value === null || value === undefined ? '—' : Number(value).toFixed(2);
    const attendanceTone = { HADIR: 'green', IZIN: 'yellow', SAKIT: 'yellow', ALPA: 'red', TERLAMBAT: 'yellow' };
    const attendanceLabel = { HADIR: 'Hadir', IZIN: 'Izin', SAKIT: 'Sakit', ALPA: 'Alpa', TERLAMBAT: 'Terlambat' };

    if (!groups.size) {
      view.innerHTML = `<div class="intro"><h1>Praktikum Saya</h1><p>${esc(user.name)} · Hasil Penilaian & Presensi Praktikum</p></div><section class="card"><div class="empty">Tidak ada kelas praktikum yang terdaftar untuk akun Anda.</div></section>`;
      return;
    }

    const selectedId = window.selectedStudentClassId && groups.has(String(window.selectedStudentClassId))
      ? String(window.selectedStudentClassId)
      : null;

    if (!selectedId) {
      view.innerHTML = `
        <div class="intro">
          <h1>Praktikum Saya</h1>
          <p>${esc(user.name)} · ${groups.size} frekuensi praktikum terdaftar sebagai praktikan</p>
        </div>
        <section class="card">
          <div class="card-head">
            <div>
              <h2>Pilih Frekuensi Praktikum</h2>
              <p>Pilih kelas praktikum di bawah untuk melihat rincian absensi kehadiran, nilai per komponen, bobot, dan nilai pertemuan.</p>
            </div>
          </div>
          ${[...groups.entries()].map(([id, group]) => {
        const r = group.first, s = group.summary, att = group.attendanceSummary;
        return `
              <button class="api-class-row" data-select-student-class="${esc(id)}">
                <span>
                  <strong>${esc(r.kode_mata_kuliah)} · ${esc(r.nama_mata_kuliah)}</strong>
                  <small>Kelompok ${esc(r.kode_kelompok)} · ${esc(r.semester)} ${esc(r.tahun_akademik)} · ${group.components.length} komponen nilai</small>
                </span>
                <span class="badge blue">Frekuensi ${esc(r.kode_kelompok)}</span>
                <span class="badge ${att.persentase >= 75 ? 'green' : (att.total_sesi ? 'yellow' : '')}">${att.total_hadir} / ${att.total_sesi} Hadir (${att.persentase}%)</span>
                ${s?.nilai_akhir != null ? `<span class="badge">${fmt(s.nilai_akhir)}${s.predikat ? ` (${esc(s.predikat)})` : ''}</span>` : '<span class="badge">Nilai berjalan</span>'}
                <span class="btn-row-arrow" aria-hidden="true">${iconSvg('arrow-right')}</span>
              </button>
            `;
      }).join('')}
        </section>
      `;
      document.querySelectorAll('[data-select-student-class]').forEach(b => {
        b.onclick = () => {
          window.selectedStudentClassId = b.dataset.selectStudentClass;
          apiStudentGrades();
        };
      });
      return;
    }

    const selectedGroup = groups.get(selectedId);
    const r = selectedGroup.first, s = selectedGroup.summary, att = selectedGroup.attendanceSummary;
    const components = selectedGroup.components, attendance = selectedGroup.attendance;
    const classMeetings = meetingGroups.get(selectedId)?.meetings || [];

    const attendanceRows = attendance.length ? `
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>PERTEMUAN</th>
              <th>TANGGAL SESI</th>
              <th>MATERI / TOPIK</th>
              <th>STATUS KEHADIRAN</th>
              <th>CATATAN</th>
            </tr>
          </thead>
          <tbody>
            ${attendance.map(a => `
              <tr>
                <td><strong>Pertemuan ${Number(a.nomor_pertemuan)}</strong></td>
                <td>${esc(a.tanggal_sesi || '—')}</td>
                <td>${esc([a.topik, a.materi].filter(Boolean).join(' · ') || '—')}</td>
                <td><span class="badge ${attendanceTone[a.status] || ''}">${esc(a.nama_status || attendanceLabel[a.status] || a.status)}</span></td>
                <td>${esc(a.catatan || '—')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    ` : '<div class="empty">Belum ada sesi presensi yang dicatat oleh Asisten.</div>';

    const componentRows = components.length ? `
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>KOMPONEN</th>
              <th>BOBOT PERSEN</th>
              <th>NILAI ANDA</th>
              <th>TANGGAL INPUT</th>
            </tr>
          </thead>
          <tbody>
            ${components.map(c => `
              <tr>
                <td>${esc(c.nama_komponen || c.kode_komponen)}</td>
                <td>${c.bobot_persen === null ? '—' : Number(c.bobot_persen) + '%'}</td>
                <td><strong>${fmt(c.nilai)}</strong></td>
                <td>${esc(c.diubah_pada || '—')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    ` : '<div class="empty">Belum ada komponen nilai akhir yang tercatat.</div>';

    const meetingRows = classMeetings.length ? classMeetings.map(meeting => `
      <div class="card-body" style="border-bottom:1px solid var(--border-color, #e2e8f0);padding-bottom:16px;margin-bottom:16px">
        <div class="row between" style="margin-bottom:8px">
          <h3 style="margin:0">Pertemuan ${Number(meeting.nomor_pertemuan)} · ${esc(meeting.tanggal_sesi || 'Tanggal belum ditentukan')}</h3>
          <span class="badge ${meeting.lengkap ? 'green' : 'yellow'}">Nilai pertemuan: ${meeting.nilai_pertemuan === null ? 'Belum lengkap' : fmt(meeting.nilai_pertemuan)}</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>KATEGORI</th>
                <th>BOBOT</th>
                <th>NILAI</th>
                <th>CATATAN</th>
              </tr>
            </thead>
            <tbody>
              ${meeting.komponen.map(component => `
                <tr>
                  <td>${esc(component.nama)}</td>
                  <td>${Number(component.bobot_persen)}%</td>
                  <td>${fmt(component.nilai)}</td>
                  <td>${esc(component.catatan || '—')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `).join('') : '<div class="empty">Belum ada nilai kategori per pertemuan.</div>';

    view.innerHTML = `
      <div class="intro">
        <div class="row between">
          <button class="small btn-back" id="student-back-to-classes">${iconSvg('arrow-left')} <span>Kembali ke daftar kelas praktikum</span></button>
          ${groups.size > 1 ? `
            <label class="inline-field" style="margin:0">
              Pindah frekuensi:
              <select id="student-class-selector">
                ${[...groups.entries()].map(([gid, g]) => `<option value="${esc(gid)}" ${gid === selectedId ? 'selected' : ''}>${esc(g.first.kode_kelompok)} · ${esc(g.first.nama_mata_kuliah)}</option>`).join('')}
              </select>
            </label>
          ` : ''}
        </div>
        <div style="margin-top:16px">
          <small class="muted">${esc(r.kode_mata_kuliah)} · Frekuensi ${esc(r.kode_kelompok)} · ${esc(r.semester)} ${esc(r.tahun_akademik)}</small>
          <h1 style="margin-top:4px">${esc(r.nama_mata_kuliah)}</h1>
          <p>Praktikan · Kelompok ${esc(r.kode_kelompok)} · Total ${att.total_hadir} dari ${att.total_sesi} sesi hadir (${att.persentase}%)</p>
        </div>
      </div>

      <section class="card">
        <div class="card-head">
          <h2>Ringkasan kehadiran & nilai</h2>
        </div>
        <div class="card-body">
          <div class="stats">
            <div class="stat">
              <span class="label">Total Kehadiran</span>
              <b>${att.total_hadir} <small style="font-weight:normal;font-size:13px">/ ${att.total_sesi} sesi</small></b>
              <small class="badge ${att.persentase >= 75 ? 'green' : (att.total_sesi ? 'yellow' : '')}">${att.persentase}% Kehadiran</small>
            </div>
            <div class="stat">
              <span class="label">Rata-rata Evaluasi</span>
              <b>${fmt(s?.rata_evaluasi)}</b>
              <small>${components.filter(c => c.kode_komponen?.startsWith('EVALUASI')).length} evaluasi dinilai</small>
            </div>
            <div class="stat">
              <span class="label">Rata-rata Asistensi</span>
              <b>${fmt(s?.rata_asistensi)}</b>
              <small>${components.filter(c => c.kode_komponen?.startsWith('ASISTENSI')).length} asistensi dinilai</small>
            </div>
            <div class="stat">
              <span class="label">Nilai Akhir</span>
              <b>${fmt(s?.nilai_akhir)}</b>
              <small>${s?.predikat ? `Predikat ${esc(s.predikat)}` : (s?.konfigurasi_nilai_tersedia ? 'Belum lengkap' : 'Konfigurasi belum tersedia')}</small>
            </div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <h2>Presensi mahasiswa · ${att.total_hadir} Hadir (${att.persentase}%)</h2>
          <p>Catatan kehadiran Anda pada setiap pertemuan praktikum.</p>
        </div>
        ${attendanceRows}
      </section>

      <section class="card">
        <div class="card-head">
          <h2>Komponen & bobot nilai akhir semester</h2>
          <p>Rincian perolehan nilai dan bobot persentase pada kelas praktikum ini.</p>
        </div>
        ${componentRows}
      </section>

      <section class="card">
        <div class="card-head">
          <h2>Nilai tiap pertemuan</h2>
          <p>Riwayat nilai kategori dan umpan balik asisten per pertemuan.</p>
        </div>
        ${meetingResult.error ? `<div class="warning">${esc(meetingResult.error)}</div>` : ''}
        ${meetingRows}
      </section>
    `;

    $('#student-back-to-classes').onclick = () => {
      window.selectedStudentClassId = null;
      apiStudentGrades();
    };
    if ($('#student-class-selector')) {
      $('#student-class-selector').onchange = e => {
        window.selectedStudentClassId = e.target.value;
        apiStudentGrades();
      };
    }
  } catch (error) {
    view.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>`;
  }
}
function hasUnsaved() { return changes.size || window.apiDraftStorageFailed || $('#meeting-date')?.dataset.dirty === '1' || $('#material')?.dataset.dirty === '1' || !!document.querySelector('#api-view [data-dirty="1"]') }
function navigate(p) { if (hasUnsaved() && !confirm('Perubahan belum tersimpan di browser atau API. Tinggalkan halaman?')) return; window.apiDraftStorageFailed = false; changes.clear(); current = null; page = p; render() }
function stats() { const students = classes.reduce((s, c) => s + c.students, 0), pending = classes.filter(c => c.state === 'Diajukan').length; return `<div class="stats">${[[classes.length, user.role === 'dosen' ? 'Kelas yang diampu' : 'Kelas praktikum', 'grid', 'Semester berjalan'], [students, 'Total mahasiswa', 'users', 'Peserta terdaftar'], [classes.reduce((s, c) => s + c.meetingCount, 0), 'Pertemuan tercatat', 'calendar', classes.reduce((n, c) => n + c.meeting_count, 0) + ' total pertemuan'], [pending, 'Menunggu verifikasi', 'shield-check', 'Pengajuan ke dosen']].map(([n, l, , h]) => `<div class="stat"><span class="label">${l}</span><b>${n}</b><small>${h}</small></div>`).join('')}</div>` }
function dashboard() { const first = user.name.split(' ')[0], date = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date()); $('#view').innerHTML = `<div class="intro row between"><div><h1>Selamat datang, ${esc(first)}</h1></div><span class="badge date-pill">${iconSvg('clock')} ${date}</span></div>${stats()}<div class="dashboard-grid"><div><section class="card"><div class="card-head row between"><h2>Jadwal Praktikum</h2><button class="small" id="all-schedules">Lihat semua ↗</button></div>${classes.slice(0, 4).map(schedule).join('') || '<div class="empty">Belum ada kelas yang ditugaskan.</div>'}</section><section class="card"><div class="card-head"><h2>Progres Pengisian</h2></div><div class="table-wrap"><table><thead><tr><th>MATA KULIAH</th><th>ABSENSI</th><th>PENILAIAN</th><th>STATUS</th></tr></thead><tbody>${classes.map(c => `<tr><td><strong>${esc(c.course)}</strong><br><small>Frekuensi ${esc(c.class)} · ${esc(c.prodi)}</small></td><td>${progress(c.attendanceCount, c.students * c.meeting_count)}</td><td>${progress(c.gradeCount, c.students * (c.evaluation_count + c.assistance_count + 2))}</td><td>${badge(c.state)}</td></tr>`).join('')}</tbody></table></div></section></div><aside class="dashboard-aside"><section class="card"><div class="card-head row between"><h2>Perlu Perhatian</h2><span class="badge yellow">${classes.filter(c => c.state === 'Perlu Revisi' || c.gradeCount < c.students * (c.evaluation_count + c.assistance_count + 2) || c.state === 'Diajukan').length}</span></div><div class="card-body">${notifications()}</div></section><section class="card action-card"><div class="card-head"><h2>Aksi Cepat</h2></div><div class="card-body" style="display:flex;flex-direction:column;gap:10px"><button class="primary small" id="open-report">${iconSvg('bar-chart')} Rekap Nilai Praktikum ↗</button></div></section></aside></div>`; bindOpen(); $('#all-schedules').onclick = () => navigate('Jadwal Praktikum'); $('#open-report').onclick = () => navigate('Laporan') }
function progress(n, d) { const p = d ? Math.round(n / d * 100) : 0; return `${p}%<div class="progress"><i style="width:${p}%"></i></div>` }
function notifications() { return classes.map(c => { const list = []; if (c.note && c.state === 'Perlu Revisi') list.push('Revisi dosen: ' + c.note); if (user.role === 'dosen' && c.state === 'Diajukan') list.push('Penilaian menunggu verifikasi Anda.'); if (c.attendanceCount < c.students * c.meeting_count) list.push(`${c.students * c.meeting_count - c.attendanceCount} absensi belum diisi.`); if (c.lowGrades) list.push(`${c.lowGrades} mahasiswa dengan nilai akhir <55%.`); if (c.gradeCount < c.students * (c.evaluation_count + c.assistance_count + 2)) list.push(`${c.students * (c.evaluation_count + c.assistance_count + 2) - c.gradeCount} nilai evaluasi, asistensi, atau ujian belum lengkap.`); if (c.lowPresence) list.push(`${c.lowPresence} mahasiswa dengan kehadiran <75%.`); return list.length ? `<div class="notice"><strong><span class="dot"></span>${esc(c.course)} · ${esc(c.class)}</strong><p>${list.map(esc).join('<br>')}</p></div>` : '' }).join('') || '<p>Semua pengisian sudah lengkap.</p>' }
function schedule(c) { return `<div class="schedule"><div class="datebox">${esc(c.day.slice(0, 3).toUpperCase())}<b>${esc(c.time.slice(0, 5))}</b></div><div class="grow"><h3>${esc(c.course)}</h3><small>Frekuensi ${esc(c.class)} &nbsp;·&nbsp; ${esc(c.lab)} &nbsp;·&nbsp; ${esc(c.time)}${c.day === new Intl.DateTimeFormat('id-ID', { weekday: 'long' }).format(new Date()) ? ' &nbsp; <span class="badge yellow">Hari ini</span>' : ''}</small><div style="margin-top:8px"><small>${esc(c.lecturer)}</small></div></div>${badge(c.active ? c.state : 'Nonaktif')}<button class="small" data-open="${c.id}">Buka kelas ↗</button></div>` }
function bindOpen() { document.querySelectorAll('[data-open]').forEach(b => b.onclick = () => openClass(Number(b.dataset.open))) }
async function openClass(id) { try { current = await api('/classes/' + id); meeting = 1; changes.clear(); tab = page === 'Penilaian' ? 'Evaluasi' : ['Laporan', 'Verifikasi'].includes(page) ? 'Rekap Nilai' : 'Absensi'; render() } catch (e) { toast(e.message) } }
function listPage() { $('#view').innerHTML = `<div class="intro"><h1>${page}</h1></div><div class="filters"><input id="search" placeholder="Cari mata kuliah, kelas, dosen, atau asisten…"><select id="prodi"><option value="">Semua program studi</option><option>Teknik Informatika</option><option>Sistem Informasi</option></select><select id="year"><option value="">Semua tahun akademik</option>${[...new Set(classes.map(c => c.year))].map(x => `<option>${esc(x)}</option>`).join('')}</select><select id="semester"><option value="">Semua semester</option><option>Ganjil</option><option>Genap</option></select><select id="status"><option value="">Semua status</option>${['Draft', 'Diajukan', 'Perlu Revisi', 'Terverifikasi', 'Dikunci'].map(x => `<option>${x}</option>`).join('')}</select></div><section class="card" id="class-list"></section>`; const update = () => { const q = $('#search').value.toLowerCase(); const rows = classes.filter(c => (!$('#prodi').value || c.prodi === $('#prodi').value) && (!$('#year').value || c.year === $('#year').value) && (!$('#semester').value || c.semester === $('#semester').value) && (!$('#status').value || c.state === $('#status').value) && [c.course, c.code, c.class, c.frequency, c.lecturer, c.assistant1, c.assistant2].join(' ').toLowerCase().includes(q)); $('#class-list').innerHTML = rows.map(schedule).join('') || '<div class="empty">Tidak ada kelas yang cocok dengan filter.</div>'; bindOpen() }; $('.filters').oninput = update; update() }
function canEdit() { return ['admin', 'asisten'].includes(user.role) && current.class.active && ['Draft', 'Perlu Revisi'].includes(current.class.state) }
function renderClass() { const c = current.class; $('#view').innerHTML = `<div class="intro"><button class="small" id="back">← Kembali ke ${esc(page)}</button><div class="row between" style="margin-top:16px"><div><small class="muted">${esc(c.code)} · ${esc(c.prodi)} · ${esc(c.year)} ${esc(c.semester)}</small><h1 style="margin-top:4px">${esc(c.course)}</h1><p>Frekuensi ${esc(c.class)} · ${esc(c.day)}, ${esc(c.time)} · ${esc(c.lab)} · ${c.sks} SKS</p></div>${badge(c.state)}</div></div>${c.note ? `<div class="warning"><strong>Catatan verifikasi:</strong> ${esc(c.note)}</div>` : ''}<section class="card"><div class="card-body summary-meta"><div><small>FREKUENSI</small>${esc(c.frequency || '—')}</div><div><small>DOSEN PENGAMPU</small>${esc(c.lecturer)}</div><div><small>ASISTEN PRAKTIKUM</small>${esc(c.assistant1)} · ${esc(c.assistant2)}</div><div><small>PESERTA</small>${current.students.length} mahasiswa <span class="muted">/ ${c.active ? 'Jadwal aktif' : 'Nonaktif'}</span></div></div><div class="tabs">${['Absensi', 'Evaluasi', 'Asistensi', 'MID', 'UAS', 'Rekap Nilai', 'Riwayat'].map(t => `<button data-tab="${t}" class="${tab === t ? 'active' : ''}">${t}</button>`).join('')}</div><div class="card-body row between"><small>${c.meeting_count} pertemuan · ${c.evaluation_count} evaluasi · ${c.assistance_count} asistensi</small>${canEdit() ? '<button class="small" id="components">Atur jumlah pengisian</button>' : ''}</div><div id="class-content"></div><div class="savebar row between"><small id="save-status">Semua perubahan tersimpan · versi ${c.version}</small><div class="row" id="class-actions">${canEdit() ? '<button class="primary" id="save">Simpan perubahan</button>' : ''}${workflowButtons()}</div></div></section>`; $('#back').onclick = () => navigate(page); document.querySelectorAll('[data-tab]').forEach(b => b.onclick = () => { if (hasUnsaved()) { toast('Simpan perubahan sebelum berpindah tab.'); return } tab = b.dataset.tab; classContent() }); if ($('#components')) $('#components').onclick = configureComponents; if ($('#save')) $('#save').onclick = save; document.querySelectorAll('[data-state]').forEach(b => b.onclick = () => workflow(b.dataset.state)); classContent() }
function workflowButtons() { const c = current.class; return `${canEdit() ? '<button id="submit" data-state="Diajukan">Ajukan ke dosen</button>' : ''}${user.role === 'dosen' && c.state === 'Diajukan' ? '<button data-state="Perlu Revisi">Minta revisi</button><button class="primary" data-state="Terverifikasi">Verifikasi nilai</button>' : ''}${user.role === 'dosen' && c.state === 'Terverifikasi' ? '<button class="dark" data-state="Dikunci">Kunci penilaian</button>' : ''}${user.role === 'admin' && ['Terverifikasi', 'Dikunci', 'Diajukan'].includes(c.state) ? '<button data-state="Draft">Buka kembali</button>' : ''}` }
function field(s, kind, n, value) { const key = `${s.id}-${kind}-${n}`, v = changes.has(key) ? changes.get(key).value : value; return `<input type="number" min="0" max="100" step="0.01" data-student="${s.id}" data-kind="${kind}" data-number="${n}" value="${esc(v ?? '')}" ${canEdit() ? '' : 'disabled'} aria-label="${esc(s.name)} ${kind} ${n}">` }
function classContent() {
  document.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('active', b.dataset.tab === tab)); const d = current, c = d.class, students = d.students; let html = ''; if (tab === 'Absensi') { const m = d.meetings.find(m => m.number === meeting); html = `<div class="card-body row"><label>Pertemuan<select id="meeting">${Array.from({ length: current.class.meeting_count }, (_, i) => `<option value="${i + 1}" ${meeting === i + 1 ? 'selected' : ''}>Pertemuan ${i + 1}</option>`).join('')}</select></label><label>Tanggal<input type="date" id="meeting-date" value="${esc(m?.date || new Date().toISOString().slice(0, 10))}" ${canEdit() ? '' : 'disabled'}></label><label style="flex:1;min-width:190px">Materi praktikum<input id="material" placeholder="Tuliskan materi pertemuan…" value="${esc(m?.material || '')}" ${canEdit() ? '' : 'disabled'}></label>${canEdit() ? '<button id="all-present" style="align-self:end">Hadirkan semua</button>' : ''}</div><div class="table-wrap"><table><thead><tr><th>NO</th><th class="name">MAHASISWA</th><th>STATUS KEHADIRAN</th><th>CATATAN</th><th>REKAP HADIR</th></tr></thead><tbody>${students.map((s, i) => { const v = changes.get(`${s.id}-attendance-${meeting}`) || s.attendance.find(a => a.number === meeting); return `<tr><td>${i + 1}</td><td class="name"><strong>${esc(s.name)}</strong><small>${s.nim}</small></td><td><select data-student="${s.id}" data-kind="attendance" data-number="${meeting}" ${canEdit() ? '' : 'disabled'}><option value="">Belum diisi</option>${[['H', 'H · Hadir'], ['I', 'I · Izin'], ['S', 'S · Sakit'], ['A', 'A · Alpa'], ['T', 'T · Terlambat']].map(([k, l]) => `<option value="${k}" ${v?.value === k ? 'selected' : ''}>${l}</option>`).join('')}</select></td><td><input class="note" data-note="${s.id}" value="${esc(v?.note || '')}" placeholder="Catatan opsional" ${canEdit() ? '' : 'disabled'}></td><td>${badge(num(s.presence) + '%')} <small>${s.attendance.filter(a => a.value === 'H').length}/${d.meetings.length} hadir</small></td></tr>` }).join('')}</tbody></table></div><div class="help">H = Hadir · I = Izin · S = Sakit · A = Alpa · T = Terlambat. Kehadiran dihitung dari status H dan pertemuan yang tercatat.</div>` }
  else if (['Evaluasi', 'Asistensi', 'MID', 'UAS'].includes(tab)) { const n = tab === 'Evaluasi' ? c.evaluation_count : tab === 'Asistensi' ? c.assistance_count : 1, kind = tab === 'Evaluasi' ? 'evaluation' : tab === 'Asistensi' ? 'assistance' : tab; html = `<div class="card-body row between"><div><h3 style="margin-bottom:6px">Nilai ${tab}</h3><small>Rentang 0–100. Kolom kosong berarti belum dinilai; nilai 0 tetap dihitung.</small></div><div class="row" style="gap:8px"><span class="badge yellow">Bobot ${['MID', 'UAS'].includes(tab) ? '30%' : '20%'}</span>${canEdit() && ['Evaluasi', 'Asistensi'].includes(tab) ? `<button class="small" id="add-component">+ Tambah ${tab}</button>` : ''}</div></div><div class="table-wrap"><table><thead><tr><th>NO</th><th class="name">MAHASISWA</th>${Array.from({ length: n }, (_, i) => `<th>${tab.toUpperCase()} ${!['MID', 'UAS'].includes(tab) ? i + 1 : ''}</th>`).join('')}${!['MID', 'UAS'].includes(tab) ? '<th>RATA-RATA</th>' : ''}</tr></thead><tbody>${students.map((s, i) => `<tr><td>${i + 1}</td><td class="name"><strong>${esc(s.name)}</strong><small>${s.nim}</small></td>${Array.from({ length: n }, (_, j) => `<td>${field(s, kind, j + 1, ['MID', 'UAS'].includes(tab) ? s[tab.toLowerCase()] : (tab === 'Evaluasi' ? s.evaluations : s.assistance).find(x => x.number === j + 1)?.value)}</td>`).join('')}${!['MID', 'UAS'].includes(tab) ? `<td><strong>${num(tab === 'Evaluasi' ? s.ev : s.as)}</strong></td>` : ''}</tr>`).join('')}</tbody></table></div>` }
  else if (tab === 'Rekap Nilai') { html = `<div class="card-body row between"><div><h3 style="margin-bottom:6px">Rekap kehadiran & nilai akhir</h3><small>Evaluasi 20% + Asistensi 20% + MID 30% + UAS 30%</small></div><div class="row"><button class="small" data-export="xlsx">Excel</button><button class="small" data-export="pdf">PDF</button><button class="small" id="print">Cetak</button></div></div><div class="table-wrap"><table><thead><tr><th>NO</th><th class="name">MAHASISWA</th><th>H / I / S / A / T</th><th>KEHADIRAN</th><th>EVALUASI</th><th>ASISTENSI</th><th>MID</th><th>UAS</th><th>NA</th><th>HURUF</th><th>STATUS</th></tr></thead><tbody>${students.map((s, i) => `<tr><td>${i + 1}</td><td class="name"><button class="small" data-detail="${s.id}">${esc(s.name)}</button><small>${s.nim}</small></td><td>${['H', 'I', 'S', 'A', 'T'].map(v => s.attendance.filter(a => a.value === v).length).join(' / ')}</td><td><span class="badge ${s.presence < 75 ? 'red' : 'green'}">${num(s.presence)}%</span></td><td>${num(s.ev)}</td><td>${num(s.as)}</td><td>${num(s.mid)}</td><td>${num(s.uas)}</td><td><strong>${num(s.na)}</strong></td><td>${badge(s.letter)}</td><td>${badge(c.state)}</td></tr>`).join('')}</tbody></table></div><div class="help">Klik nama mahasiswa untuk melihat detail dan riwayat input. Nilai akhir belum tersedia jika salah satu komponen belum dinilai.</div>` }
  else html = auditTable(d.history);
  $('#class-content').innerHTML = html; if ($('#add-component')) $('#add-component').onclick = () => addComponent(); if (!students.length && tab !== 'Riwayat') $('#class-content').insertAdjacentHTML('beforeend', '<div class="empty">Belum ada peserta. Admin dapat menyinkronkan mahasiswa dari Data Master.</div>');
  document.querySelectorAll('[data-kind]').forEach(el => el.onchange = () => { const s = Number(el.dataset.student), kind = el.dataset.kind, n = Number(el.dataset.number); if (kind !== 'attendance' && !el.checkValidity()) { el.reportValidity(); return } if (kind === 'attendance' && !el.value) { toast('Pilih status H, I, S, A, atau T.'); return } changes.set(`${s}-${kind}-${n}`, { student: s, kind, number: n, value: kind === 'attendance' ? el.value : el.value === '' ? null : Number(el.value), ...(kind === 'attendance' ? { note: document.querySelector(`[data-note="${s}"]`).value } : {}) }); dirty() });
  document.querySelectorAll('[data-note]').forEach(el => el.onchange = () => { const select = document.querySelector(`[data-student="${el.dataset.note}"]`); select.onchange() }); if ($('#meeting')) { $('#meeting').onchange = e => { if (hasUnsaved()) { e.target.value = meeting; toast('Simpan perubahan sebelum berpindah pertemuan.'); return } meeting = Number(e.target.value); classContent() }; for (const id of ['#material', '#meeting-date']) $(id).oninput = () => { $(id).dataset.dirty = '1'; dirty() }; if ($('#all-present')) $('#all-present').onclick = () => { document.querySelectorAll('[data-kind="attendance"]').forEach(el => { el.value = 'H'; el.onchange() }) } }
  document.querySelectorAll('[data-export]').forEach(b => b.onclick = () => download(b.dataset.export)); if ($('#print')) $('#print').onclick = () => window.print(); document.querySelectorAll('[data-detail]').forEach(b => b.onclick = () => studentDetail(Number(b.dataset.detail)))
}
function dirty() { $('#save-status').textContent = `● Perubahan belum disimpan (${changes.size} data)`; $('#save-status').style.color = '#a68719' }
async function save() { try { if (document.querySelector('input:invalid')) { document.querySelector('input:invalid').reportValidity(); return } const b = { version: current.class.version, changes: [...changes.values()] }; if (tab === 'Absensi') b.meeting = { number: meeting, date: $('#meeting-date').value, material: $('#material').value }; await api('/classes/' + current.class.id + '/save', b); changes.clear(); current = await api('/classes/' + current.class.id); classes = await api('/classes'); renderClass(); toast('Perubahan berhasil disimpan.') } catch (e) { toast(e.message) } }
function dialog(html) { const m = $('#modal'); m.innerHTML = `<button type="button" class="modal-close-btn" data-close aria-label="Tutup">${iconSvg('close')}</button>${html}`; m.showModal(); m.querySelectorAll('[data-close]').forEach(b => b.onclick = () => m.close()); return m }
function workflow(state) { if (hasUnsaved()) { toast('Simpan perubahan sebelum mengubah status.'); return } const m = dialog(`<form><h2>${esc(state === 'Draft' ? 'Buka kembali penilaian' : state)}</h2><p>Perubahan status berlaku untuk seluruh peserta kelas ${esc(current.class.class)}.</p><label>Catatan / alasan<textarea name="note" rows="3" ${['Draft', 'Perlu Revisi'].includes(state) ? 'required' : ''}></textarea></label><div class="row"><button type="button" data-close>Batal</button><button class="primary">Konfirmasi ${esc(state)}</button></div></form>`); m.querySelector('form').onsubmit = async e => { e.preventDefault(); try { await api('/classes/' + current.class.id + '/workflow', { state, note: new FormData(e.target).get('note'), version: current.class.version }); m.close(); current = await api('/classes/' + current.class.id); classes = await api('/classes'); renderClass(); toast('Status penilaian diperbarui.') } catch (x) { toast(x.message) } } }
async function download(format) { try { const r = await fetch(apiUrl(`/classes/${current.class.id}/export?format=${format}`)); if (!r.ok) throw Error((await r.json()).error); const u = URL.createObjectURL(await r.blob()); const a = document.createElement('a'); a.href = u; a.download = `Praktikum-${current.class.code}-${current.class.class}.${format}`; a.click(); setTimeout(() => URL.revokeObjectURL(u), 5000) } catch (e) { toast(e.message) } }
function auditTable(rows) { return `<div class="table-wrap"><table><thead><tr><th>WAKTU</th><th>PENGGUNA</th><th>PERUBAHAN</th><th>SEBELUM</th><th>SESUDAH</th></tr></thead><tbody>${rows.map(r => `<tr><td>${new Date(r.created_at).toLocaleString('id-ID')}</td><td>${esc(r.name)}<br><small>${esc(roleLabel[r.role] || ({ asisten1: 'Asisten (posisi lama 1)', asisten2: 'Asisten (posisi lama 2)' })[r.role] || r.role)}</small></td><td>${esc(r.entity)}</td><td class="audit">${esc(r.old_value)}</td><td class="audit">${esc(r.new_value)}</td></tr>`).join('')}</tbody></table>${rows.length ? '' : '<div class="empty">Belum ada perubahan data.</div>'}</div>` }
function studentDetail(id) { const s = current.students.find(s => s.id === id); dialog(`<div class="row between"><h2>${esc(s.name)}</h2></div><p>${s.nim} · Frekuensi ${esc(current.class.class)}</p><div class="student-detail"><div><strong>Absensi 1–${current.class.meeting_count}</strong><br>${Array.from({ length: current.class.meeting_count }, (_, i) => `${i + 1}: ${s.attendance.find(a => a.number === i + 1)?.value || '—'}`).join(' · ')}</div><div><strong>Evaluasi 1–${current.class.evaluation_count}</strong><br>${Array.from({ length: current.class.evaluation_count }, (_, i) => `${i + 1}: ${s.evaluations.find(a => a.number === i + 1)?.value ?? '—'}`).join(' · ')}</div><div><strong>Asistensi 1–${current.class.assistance_count}</strong><br>${Array.from({ length: current.class.assistance_count }, (_, i) => `${i + 1}: ${s.assistance.find(a => a.number === i + 1)?.value ?? '—'}`).join(' · ')}</div><div><strong>Ujian & nilai akhir</strong><br>MID ${num(s.mid)} · UAS ${num(s.uas)}<br>NA ${num(s.na)} (${s.letter})</div></div><h3 style="margin-top:25px">Riwayat input mahasiswa</h3>${auditTable(current.history.filter(r => r.entity.startsWith(id + ':')))}<div class="row modal-actions" style="justify-content:flex-end"><button type="button" class="primary" data-close>Tutup</button></div>`) }
async function logout() {
  if (hasUnsaved() && !confirm('Ada perubahan belum disimpan. Tetap logout?')) return;
  const buttons = [$('#logout'), $('#header-logout')].filter(Boolean);
  buttons.forEach(b => b.disabled = true);
  try {
    await api('/logout', {});
    current = null; classes = []; masterData = null; changes.clear(); page = 'Dashboard'; tab = 'Absensi'; meeting = 1;
    if ($('#modal').open) $('#modal').close();
    login(); toast('Anda berhasil logout.');
  } catch (e) { buttons.forEach(b => b.disabled = false); toast(e.message) }
}
function account() { const m = dialog('<form><h2>Ganti password</h2><label>Password saat ini<input name="current" type="password" required autocomplete="current-password"></label><label>Password baru (minimal 6 karakter)<input name="password" type="password" minlength="6" required autocomplete="new-password"></label><div class="row"><button type="button" data-close>Batal</button><button class="primary">Simpan password</button></div></form>'); m.querySelector('form').onsubmit = async e => { e.preventDefault(); try { await api('/password', Object.fromEntries(new FormData(e.target))); m.close(); toast('Password diperbarui.') } catch (x) { toast(x.message) } } }
const masterLabels = { periode_aktif: 'Periode Semester Aktif', mahasiswa: 'Mahasiswa', jadwal_praktikum: 'Jadwal Praktikum', users: 'Akun Pengguna', mata_kuliah: 'Mata Kuliah', anggota_frekuensi: 'Master Frekuensi Praktikum', frekuensi_praktikum: 'Daftar Frekuensi', laboratorium: 'Laboratorium', tahun_akademik: 'Tahun Akademik', semester: 'Semester', prodi: 'Program Studi', bands: 'Konfigurasi Nilai' };
const fields = { mahasiswa: ['nim', 'name', 'prodi_id', 'kelas_text', 'status'], jadwal_praktikum: ['tahun_id', 'semester_id', 'mk_id', 'prodi_id', 'lab_id', 'dosen_id', 'asisten1_id', 'asisten2_id', 'day', 'time', 'frequency', 'active'], users: ['name', 'email', 'role', 'password'], mata_kuliah: ['code', 'name', 'sks'], frekuensi_praktikum: ['name', 'prodi_id'], laboratorium: ['name'], tahun_akademik: ['name'], semester: ['name'], prodi: ['name'] };
const fieldLabels = { kelas_text: 'Kelas', frequency: 'Frekuensi', nim: 'Stambuk / NIM', name: 'Nama', prodi_id: 'Program Studi', kelas_id: 'Kelas', status: 'Status', tahun_id: 'Tahun Akademik', semester_id: 'Semester', mk_id: 'Mata Kuliah', lab_id: 'Laboratorium', dosen_id: 'Dosen Pengampu', asisten1_id: 'Asisten 1', asisten2_id: 'Asisten 2 (opsional)', day: 'Hari', time: 'Jam (07.00–10.20)', active: 'Aktif', code: 'Kode MK', sks: 'SKS', email: 'Email', role: 'Role', password: 'Password (min. 6 karakter)' };
const relations = { prodi_id: 'prodi', kelas_id: 'kelas', tahun_id: 'tahun_akademik', semester_id: 'semester', mk_id: 'mata_kuliah', lab_id: 'laboratorium', dosen_id: 'dosen', asisten1_id: 'asisten', asisten2_id: 'asisten' };
async function masters() { try { masterData = await api('/admin/data'); if (masterTab === 'periode_aktif') { activePeriod = await api('/active-period'); periodMaster(); return; } if (masterTab === 'anggota_frekuensi') { frequencyMaster(); return; } $('#view').innerHTML = `<div class="intro"><h1>Data Master</h1></div><div class="master-tabs">${Object.entries(masterLabels).map(([k, l]) => `<button class="${masterTab === k ? 'active' : ''}" data-master="${k}">${l}</button>`).join('')}</div><section class="card"><div class="card-head row between"><h2>${masterLabels[masterTab]}</h2><div class="row">${masterTab === 'users' ? '<button class="small" id="import-users">↑ Import Excel</button>' : ''}${masterTab === 'jadwal_praktikum' ? '<button class="small" id="import-schedule">↑ Import jadwal</button>' : ''}${false ? '<button class="small" id="import">↑ Import Excel</button>' : ''}${masterTab === 'bands' ? '<button class="primary small" id="save-bands">Simpan ambang</button>' : '<button class="primary small" id="add">+ Tambah data</button>'}</div></div><div id="master-content"></div></section>`; document.querySelectorAll('[data-master]').forEach(b => b.onclick = () => { masterTab = b.dataset.master; masters() }); if (masterTab === 'bands') { $('#master-content').innerHTML = '<div class="card-body"><p>Batas bawah inklusif. Nilai menggunakan ambang tertinggi yang terpenuhi.</p>' + masterData.bands.map(b => `<label style="display:inline-grid;margin:10px">${b.letter}<input type="number" min="0" max="100" step="0.01" data-band="${b.letter}" value="${b.min}" style="width:90px"></label>`).join('') + '</div>'; $('#save-bands').onclick = async () => { try { await api('/admin/bands', { bands: [...document.querySelectorAll('[data-band]')].map(el => ({ letter: el.dataset.band, min: Number(el.value) })) }); toast('Konfigurasi tersimpan.') } catch (e) { toast(e.message) } }; return } const cols = (masterTab === 'users' ? ['no', 'name', 'email', 'role', 'nim', 'active'] : ['no', ...fields[masterTab]]); $('#master-content').innerHTML = `<div class="table-wrap"><table><thead><tr>${cols.map(c => `<th>${esc(c === 'no' ? 'No' : fieldLabels[c] || c)}</th>`).join('')}<th>AKSI</th></tr></thead><tbody>${masterData[masterTab].map((r, index) => `<tr>${cols.map(c => `<td>${esc(c === 'no' ? index + 1 : relations[c] ? masterData[relations[c]].find(x => x.id === r[c])?.name || r[c] : r[c])}</td>`).join('')}<td>${masterTab === 'users' ? `<button class="small" data-user="${r.id}" data-active="${r.active ? 0 : 1}">${r.active ? 'Nonaktifkan' : 'Aktifkan'}</button>` : `<button class="small" data-edit="${r.id}">Edit</button>`}${masterTab === 'jadwal_praktikum' ? ` <button class="small" data-sync="${r.id}">Sinkron peserta</button>` : ''}</td></tr>`).join('')}</tbody></table></div>`; masterSearch(); $('#add').onclick = () => editMaster(); document.querySelectorAll('[data-edit]').forEach(b => b.onclick = () => editMaster(masterData[masterTab].find(r => r.id === Number(b.dataset.edit)))); document.querySelectorAll('[data-sync]').forEach(b => b.onclick = async () => { try { await api('/classes/' + b.dataset.sync + '/sync', {}); classes = await api('/classes'); toast('Peserta kelas berhasil disinkronkan.') } catch (e) { toast(e.message) } }); document.querySelectorAll('[data-user]').forEach(b => b.onclick = async () => { try { await api('/admin/user-status', { id: Number(b.dataset.user), active: Number(b.dataset.active) }); masters() } catch (e) { toast(e.message) } }); if ($('#import-users')) $('#import-users').onclick = importUsers; if ($('#import-schedule')) $('#import-schedule').onclick = importSchedule; if ($('#import')) $('#import').onclick = importExcel } catch (e) { toast(e.message) } }
function editMaster(row) { if (masterTab === 'users') { createUser(); return } const m = dialog(`<form><h2>${row ? 'Edit' : 'Tambah'} ${masterLabels[masterTab]}</h2><div class="form-grid">${fields[masterTab].map(f => { let options = f === 'asisten2_id' ? [['', 'Tidak ada (opsional)'], ...masterData.asisten.map(x => [x.id, x.name])] : f === 'frequency' ? masterData.frekuensi_praktikum.map(x => [x.name, x.name]) : relations[f] ? masterData[relations[f]].map(x => [x.id, x.name]) : f === 'role' ? Object.entries(roleLabel) : f === 'day' ? ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map(x => [x, x]) : f === 'active' ? [[1, 'Aktif'], [0, 'Nonaktif']] : f === 'status' ? [['Aktif', 'Aktif'], ['Nonaktif', 'Nonaktif']] : null; return `<label>${fieldLabels[f]}${options ? `<select name="${f}">${options.map(([v, l]) => `<option value="${esc(v)}" ${String(row?.[f]) === String(v) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>` : `<input name="${f}" required value="${esc(row?.[f] || '')}" ${f === 'password' ? 'type="password" minlength="6"' : f === 'email' ? 'type="email"' : f === 'sks' ? 'type="number" min="1"' : ''}>`}</label>` }).join('')}</div><div class="row"><button type="button" data-close>Batal</button><button class="primary">Simpan data</button></div></form>`); m.querySelector('form').onsubmit = async e => { e.preventDefault(); const values = Object.fromEntries(new FormData(e.target)); for (const k in values) if (k.endsWith('_id') || ['active', 'sks'].includes(k)) values[k] = k === 'asisten2_id' && values[k] === '' ? null : Number(values[k]); try { await api('/admin/' + (masterTab === 'users' ? 'users' : 'master'), masterTab === 'users' ? values : { table: masterTab, id: row?.id, values }); m.close(); classes = await api('/classes'); masters(); toast('Data berhasil disimpan.') } catch (x) { toast(x.message) } } }
function importExcel() { const m = dialog(`<form><h2>Import mahasiswa dari Excel</h2><p>Gunakan file .xlsx dengan judul kolom <strong>NIM</strong> dan <strong>Nama</strong> pada baris pertama. NIM harus berupa teks. Seluruh baris dibatalkan bila ada NIM duplikat.</p><label>Kelas tujuan<select name="kelas_id">${masterData.kelas.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}</select></label><label>File Excel<input id="xlsx-file" type="file" accept=".xlsx" required></label><p>Sesudah import, gunakan “Sinkron peserta” pada jadwal praktikum.</p><div class="row"><button type="button" data-close>Batal</button><button class="primary">Import mahasiswa</button></div></form>`); m.querySelector('form').onsubmit = async e => { e.preventDefault(); const f = $('#xlsx-file').files[0]; if (f.size > 4e6) { toast('Ukuran file maksimal 4 MB.'); return } const reader = new FileReader(); reader.onload = async () => { try { const r = await api('/admin/import', { base64: reader.result.split(',')[1], kelas_id: Number(new FormData(e.target).get('kelas_id')) }); m.close(); masters(); toast(r.count + ' mahasiswa berhasil diimport.') } catch (x) { toast(x.message) } }; reader.readAsDataURL(f) } }
async function auditPage() { const view = user?.api_mode ? $('#api-view') : $('#view'); if (!view) return; try { const rows = await api('/admin/audit'); view.innerHTML = `<div class="intro"><div class="intro-head"><h1>Log Aktivitas</h1><p>Riwayat perubahan transaksi kelas, presensi, penilaian, dan konfigurasi pada sistem praktikum.</p></div></div><section class="card">${auditTable(rows)}</section>` } catch (e) { toast(e.message) } }
async function gradeConfigPage() { const view = $('#api-view'); view.innerHTML = '<div class="empty">Memuat ambang nilai dari API pusat…</div>'; try { const data = await api('/admin/grade-bands'); view.innerHTML = `<div class="intro"><div class="intro-head"><h1>Konfigurasi Nilai</h1><p>Tetapkan nilai minimum untuk setiap predikat huruf pada perhitungan nilai akhir praktikum.</p></div></div><section class="card"><div class="card-head"><h2>Ambang predikat</h2><p>Versi konfigurasi ${Number(data.versi)}. Predikat dipilih dari nilai minimum tertinggi yang terpenuhi.</p></div><form id="grade-band-form"><div class="table-wrap"><table><thead><tr><th>Predikat</th><th class="num">Nilai minimum</th></tr></thead><tbody>${data.rentang.map(b => `<tr><td><strong>${esc(b.kode)}</strong></td><td class="num"><input data-grade-code="${esc(b.kode)}" type="number" min="0" max="100" step="0.01" value="${Number(b.nilai_minimum)}" required></td></tr>`).join('')}</tbody></table></div><div class="card-body row" style="justify-content:flex-end"><button type="submit" class="primary">${iconSvg('save')} <span>Simpan ambang</span></button></div></form></section>`; $('#grade-band-form').onsubmit = async event => { event.preventDefault(); const rentang = [...event.currentTarget.querySelectorAll('[data-grade-code]')].map(input => ({ kode: input.dataset.gradeCode, nilai_minimum: Number(input.value) })); try { await api('/admin/grade-bands', { versi: Number(data.versi), rentang }, 'PUT'); toast('Konfigurasi nilai tersimpan.'); await gradeConfigPage() } catch (error) { toast(error.message) } } } catch (error) { view.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>` } }
async function periodManagementPage() { const view = $('#api-view'); view.innerHTML = '<div class="empty">Memuat periode akademik dari API pusat…</div>'; try { const data = await api('/admin/periods'), periods = Array.isArray(data.periode) ? data.periode : [], semesters = Array.isArray(data.semester) ? data.semester : []; view.innerHTML = `<div class="intro"><div class="intro-head"><h1>Periode Akademik</h1><p>Kelola siklus semester aktif, rentang pelaksanaan praktikum, dan unduh rekap hasil studi Excel.</p></div></div><section class="card"><div class="card-head row between"><div><h2>Siklus akademik</h2><p>Satu periode operasional aktif pada satu waktu. Periode aktif menerima transaksi Praktikum baru.</p></div><button class="primary small" id="period-create">${iconSvg('plus')} <span>Buat periode</span></button></div><div class="table-wrap"><table><thead><tr><th>Tahun ajaran</th><th>Semester</th><th>Rentang pelaksanaan</th><th class="num">Kelas</th><th>Status</th><th class="col-actions">Aksi</th></tr></thead><tbody>${periods.map(period => `<tr><td><strong>${esc(period.tahun_akademik)}</strong></td><td>${esc(period.semester?.nama || '—')} <small>(${esc(period.semester?.kode || '—')})</small></td><td>${esc(period.tanggal_mulai)} – ${esc(period.tanggal_selesai)}</td><td class="num">${Number(period.jumlah_kelas || 0)}</td><td><span class="badge ${period.aktif ? 'green' : ''}">${period.aktif ? 'Aktif' : 'Ditutup'}</span></td><td class="col-actions"><div class="row" style="gap:6px;flex-wrap:wrap;justify-content:flex-end"><button class="small btn-subtle-teal" data-period-export="${Number(period.id_periode_akademik)}" title="Unduh rekap nilai & presensi periode dalam format Excel multi-sheet">${iconSvg('download')} <span>Unduh Rekap Excel</span></button>${period.aktif ? '<span class="help" style="margin:0">Periode berjalan</span>' : `<button class="small" data-period-edit="${Number(period.id_periode_akademik)}">${iconSvg('edit')} <span>Ubah tanggal</span></button> <button class="small primary" data-period-activate="${Number(period.id_periode_akademik)}">${iconSvg('check-circle')} <span>Aktifkan</span></button>`}</div></td></tr>`).join('') || '<tr><td colspan="6" class="empty">Belum ada periode akademik.</td></tr>'}</tbody></table></div></section>`; $('#period-create').onclick = () => periodForm(semesters); document.querySelectorAll('[data-period-export]').forEach(button => button.onclick = () => { const pId = Number(button.dataset.periodExport); const period = periods.find(item => Number(item.id_periode_akademik) === pId); const label = period ? `${period.tahun_akademik}-${period.semester?.nama || ''}` : pId; downloadPeriodRecap(pId, label); }); document.querySelectorAll('[data-period-edit]').forEach(button => button.onclick = () => { const period = periods.find(item => Number(item.id_periode_akademik) === Number(button.dataset.periodEdit)); periodForm(semesters, period) }); document.querySelectorAll('[data-period-activate]').forEach(button => button.onclick = async () => { const period = periods.find(item => Number(item.id_periode_akademik) === Number(button.dataset.periodActivate)); if (!period || !confirm(`Aktifkan ${period.tahun_akademik} ${period.semester.nama}? Periode lain akan ditutup untuk transaksi baru, tanpa menghapus histori.`)) return; button.disabled = true; try { await api(`/admin/periods/${Number(period.id_periode_akademik)}/activate`, {}, 'POST'); activePeriod = await api('/active-period'); selectedAcademicPeriod = ''; classes = user.role === 'mahasiswa' ? [] : await api('/classes'); toast('Periode aktif diperbarui. Histori lama tetap tersimpan.'); periodManagementPage(); renderPeriodSummary() } catch (error) { toast(error.message); button.disabled = false } }) } catch (error) { view.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>` } }
async function downloadPeriodRecap(periodId, periodLabel) {
  toast('Menyiapkan rekap periode akademik…');
  try {
    const response = await fetch(apiUrl(`/admin/periods/${periodId}/export`), { credentials: 'same-origin' });
    if (!response.ok) {
      let message = 'Rekap periode tidak dapat diunduh.';
      try { const errJson = await response.json(); message = errJson.error || message } catch (e) { }
      throw Error(message);
    }
    const cd = response.headers.get('Content-Disposition') || '';
    const match = cd.match(/filename="?([^"]+)"?/);
    const filename = match ? match[1] : `Rekap-Hasil-Studi-${periodLabel || periodId}.xlsx`;
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
    toast('Unduhan rekap hasil studi periode berhasil dimulai.');
  } catch (error) {
    toast(error.message);
  }
}
function periodForm(semesters, period) { const semesterOptions = semesters.map(semester => `<option value="${Number(semester.id)}" ${Number(period?.semester?.id) === Number(semester.id) ? 'selected' : ''}>${esc(semester.nama)} (${esc(semester.kode)})</option>`).join(''); const modal = dialog(`<form><h2>${period ? 'Ubah periode' : 'Buat periode akademik'}</h2>${period ? '<p>Periode yang sudah memiliki kelas tidak dapat diubah. Periode aktif juga dikunci.</p>' : '<p>Periode baru dibuat nonaktif. Data batch sebelumnya tidak akan disalin.</p>'}<label>Tahun akademik<input name="tahun_akademik" type="text" placeholder="2027/2028" pattern="[0-9]{4}/[0-9]{4}" value="${esc(period?.tahun_akademik || '')}" ${period ? 'readonly' : ''} required></label>${period ? '' : `<label>Semester<select name="id_semester" required>${semesterOptions}</select></label>`}<div class="form-grid"><label>Tanggal mulai<input name="tanggal_mulai" type="date" value="${esc(period?.tanggal_mulai || '')}" required></label><label>Tanggal selesai<input name="tanggal_selesai" type="date" value="${esc(period?.tanggal_selesai || '')}" required></label></div><div class="row"><button type="button" data-close>Batal</button><button class="primary" type="submit">${period ? 'Simpan' : 'Buat periode'}</button></div></form>`); modal.querySelector('form').onsubmit = async event => { event.preventDefault(); const values = Object.fromEntries(new FormData(event.currentTarget)); if (!period) values.id_semester = Number(values.id_semester); const button = event.currentTarget.querySelector('[type=submit]'); button.disabled = true; try { await api(period ? `/admin/periods/${Number(period.id_periode_akademik)}` : '/admin/periods', values, period ? 'PATCH' : 'POST'); modal.close(); toast(period ? 'Tanggal periode diperbarui.' : 'Periode dibuat sebagai nonaktif.'); periodManagementPage() } catch (error) { toast(error.message); button.disabled = false } } }
function renderPeriodSummary() { const summary = $('#api-period-summary'); if (summary) summary.innerHTML = apiPeriodSummary() }
let accessSearchTimer = null, accessFilterState = { cari: '', kode_peran: '', status_akun: '' };
async function accessManagementPage() {
  const view = $('#api-view');
  view.innerHTML = '<div class="empty">Memuat data pengguna dan peran dari API pusat…</div>';
  try {
    const roles = await api('/admin/access-management/roles');
    const query = new URLSearchParams();
    if (accessFilterState.cari) query.set('cari', accessFilterState.cari);
    if (accessFilterState.kode_peran) query.set('kode_peran', accessFilterState.kode_peran);
    if (accessFilterState.status_akun) query.set('status_akun', accessFilterState.status_akun);
    const qs = query.toString() ? `?${query.toString()}` : '';
    const users = await api(`/admin/access-management/users${qs}`);
    const roleOptions = roles.map(r => `<option value="${esc(r.kode)}" ${accessFilterState.kode_peran === r.kode ? 'selected' : ''}>${esc(r.nama)}</option>`).join('');
    const statusOptions = [['', 'Semua status akun'], ['AKTIF', 'Aktif'], ['PENDING_AKTIVASI', 'Menunggu Aktivasi'], ['TERKUNCI', 'Terkunci'], ['DITANGGUHKAN', 'Ditangguhkan']].map(([val, label]) => `<option value="${esc(val)}" ${accessFilterState.status_akun === val ? 'selected' : ''}>${esc(label)}</option>`).join('');
    const statusTone = { AKTIF: 'green', PENDING_AKTIVASI: 'yellow', TERKUNCI: 'red', DITANGGUHKAN: 'red' };
    view.innerHTML = `
      <div class="intro">
        <h1>Manajemen Akses</h1>
        <p>Kelola akun pengguna, penetapan peran kanonikal, cakupan laboratorium, dan otorisasi sistem ICLabs.</p>
      </div>
      <section class="card">
        <div class="card-head row between">
          <div>
            <h2>Daftar Pengguna</h2>
            <p>${users.length} akun ditemukan berdasarkan filter.</p>
          </div>
          <div class="row" style="gap:8px">
            <button class="small" id="access-bulk-assign">${iconSvg('users')} <span>Penetapan Peran Massal</span></button>
            <button class="primary small" id="access-create-user">${iconSvg('user-plus')} <span>Tambah Pengguna</span></button>
          </div>
        </div>
        <div class="card-body row" style="gap:10px;flex-wrap:wrap">
          <input id="access-search" type="search" placeholder="Cari nama, email, username, NIM…" value="${esc(accessFilterState.cari)}" style="flex:1;min-width:220px">
          <select id="access-role-filter" style="width:auto;min-width:180px">
            <option value="">Semua peran</option>
            ${roleOptions}
          </select>
          <select id="access-status-filter" style="width:auto;min-width:180px">
            ${statusOptions}
          </select>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>PENGGUNA</th>
                <th>EMAIL & IDENTITAS</th>
                <th>STATUS</th>
                <th>PERAN AKTIF</th>
                <th class="col-actions">AKSI</th>
              </tr>
            </thead>
            <tbody>
              ${users.map(u => {
      const tone = statusTone[u.status_akun?.kode] || '';
      const roleBadges = (u.peran || []).map(p => `<span class="badge blue" title="Diberikan ${esc(p.mulai_pada || '')}">${esc(p.peran?.nama || p.peran?.kode || 'Peran')}</span>`).join(' ') || '<span class="muted">Belum ada peran</span>';
      return `<tr>
                  <td>
                    <strong>${esc(u.nama_lengkap || 'Pengguna')}</strong>
                    ${u.username ? `<br><small class="muted">@${esc(u.username)}</small>` : ''}
                  </td>
                  <td>
                    <span>${esc(u.email_login)}</span>
                    ${u.nim ? `<br><small class="muted">NIM: ${esc(u.nim)}</small>` : ''}
                  </td>
                  <td><span class="badge ${tone}">${esc(u.status_akun?.nama || u.status_akun?.kode || '—')}</span></td>
                  <td>${roleBadges}</td>
                  <td class="col-actions">
                    <button class="small btn-table-action" data-access-user="${Number(u.id)}">${iconSvg('sliders')} <span>Detail &amp; Kelola</span></button>
                  </td>
                </tr>`;
    }).join('') || '<tr><td colspan="5" class="empty">Tidak ada pengguna yang cocok dengan kriteria pencarian.</td></tr>'}
            </tbody>
          </table>
        </div>
      </section>
    `;
    $('#access-search').oninput = e => {
      clearTimeout(accessSearchTimer);
      accessFilterState.cari = e.target.value.trim();
      accessSearchTimer = setTimeout(() => accessManagementPage(), 350);
    };
    $('#access-role-filter').onchange = e => {
      accessFilterState.kode_peran = e.target.value;
      accessManagementPage();
    };
    $('#access-status-filter').onchange = e => {
      accessFilterState.status_akun = e.target.value;
      accessManagementPage();
    };
    $('#access-create-user').onclick = () => openCreateUserModal(roles);
    $('#access-bulk-assign').onclick = () => openBulkAssignModal(roles, users);
    document.querySelectorAll('[data-access-user]').forEach(btn => {
      btn.onclick = () => {
        const u = users.find(x => Number(x.id) === Number(btn.dataset.accessUser));
        if (u) openUserDetailModal(u, roles);
      };
    });
  } catch (error) {
    view.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>`;
  }
}
function openCreateUserModal(roles) {
  const assignableRoles = user?.is_super_admin ? roles : roles.filter(r => r.kode !== 'SUPER_ADMIN');
  const roleOptions = assignableRoles.map(r => `<option value="${esc(r.kode)}">${esc(r.nama)} (${esc(r.kode)})</option>`).join('');
  const modal = dialog(`
    <form>
      <h2>Tambah Pengguna Baru</h2>
      <p>Membuat akun internal baru berstatus <em>Menunggu Aktivasi</em> dan menetapkan peran awal.</p>
      <label>Nama lengkap
        <input name="nama_lengkap" type="text" maxlength="200" required placeholder="Nama lengkap pengguna">
      </label>
      <label>Email login
        <input name="email_login" type="email" maxlength="254" required placeholder="nama@umi.ac.id">
      </label>
      <label>Username (opsional)
        <input name="username" type="text" maxlength="100" pattern="[a-zA-Z0-9_-]+" placeholder="username_pengguna">
      </label>
      <label>Peran awal
        <select name="kode_peran" required>
          ${roleOptions}
        </select>
      </label>
      <label>Catatan pemberian peran
        <textarea name="catatan_pemberian" rows="2" maxlength="500" placeholder="Catatan awal pemberian peran (opsional)"></textarea>
      </label>
      <div class="row">
        <button type="button" data-close>Batal</button>
        <button class="primary" type="submit">Buat Pengguna</button>
      </div>
    </form>
  `);
  modal.querySelector('form').onsubmit = async event => {
    event.preventDefault();
    const submitBtn = event.currentTarget.querySelector('[type="submit"]');
    submitBtn.disabled = true;
    const values = Object.fromEntries(new FormData(event.currentTarget));
    if (!values.username) delete values.username;
    if (!values.catatan_pemberian) delete values.catatan_pemberian;
    try {
      const res = await api('/admin/access-management/users', values, 'POST');
      modal.close();
      const activationMsg = res.email_aktivasi?.pesan || '';
      toast(`Pengguna ${esc(values.nama_lengkap)} berhasil didaftarkan. ${activationMsg}`);
      accessManagementPage();
    } catch (err) {
      toast(err.message);
      submitBtn.disabled = false;
    }
  };
}
function openBulkAssignModal(roles, users) {
  const assignableRoles = roles.filter(r => r.kode !== 'SUPER_ADMIN');
  const roleOptions = assignableRoles.map(r => `<option value="${esc(r.kode)}">${esc(r.nama)} (${esc(r.kode)})</option>`).join('');
  const eligibleUsers = users.filter(u => u.dapat_diberi_peran && Number(u.id) !== Number(user?.id));
  const modal = dialog(`
    <form>
      <h2>Penetapan Peran Massal</h2>
      <p>Tetapkan satu peran kanonikal kepada beberapa pengguna aktif atau menunggu aktivasi sekaligus (maksimal 100 pengguna).</p>
      <label>Pilih peran tujuan
        <select name="kode_peran" required>
          ${roleOptions}
        </select>
      </label>
      <label>Catatan pemberian
        <input name="catatan_pemberian" type="text" maxlength="500" placeholder="Alasan pemberian peran massal (opsional)">
      </label>
      <div style="margin: 12px 0">
        <label><strong>Pilih akun target:</strong></label>
        <div class="table-wrap" style="max-height: 220px; overflow-y: auto; border: 1px solid var(--border)">
          <table>
            <thead>
              <tr>
                <th style="width:40px"><input type="checkbox" id="bulk-select-all"></th>
                <th>Nama Pengguna</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              ${eligibleUsers.map(u => `
                <tr>
                  <td><input type="checkbox" name="id_pengguna" value="${Number(u.id)}"></td>
                  <td>${esc(u.nama_lengkap)}</td>
                  <td>${esc(u.email_login)}</td>
                </tr>
              `).join('') || '<tr><td colspan="3" class="empty">Tidak ada pengguna yang memenuhi syarat.</td></tr>'}
            </tbody>
          </table>
        </div>
      </div>
      <div class="row">
        <button type="button" data-close>Batal</button>
        <button class="primary" type="submit" id="bulk-submit-btn">Tetapkan Peran</button>
      </div>
    </form>
  `);
  const selectAll = modal.querySelector('#bulk-select-all');
  if (selectAll) {
    selectAll.onchange = e => {
      modal.querySelectorAll('input[name="id_pengguna"]').forEach(cb => cb.checked = e.target.checked);
    };
  }
  modal.querySelector('form').onsubmit = async event => {
    event.preventDefault();
    const checked = [...modal.querySelectorAll('input[name="id_pengguna"]:checked')].map(cb => Number(cb.value));
    if (!checked.length) {
      toast('Pilih minimal satu akun pengguna.');
      return;
    }
    const roleCode = modal.querySelector('select[name="kode_peran"]').value;
    const note = modal.querySelector('input[name="catatan_pemberian"]').value.trim();
    const submitBtn = modal.querySelector('#bulk-submit-btn');
    submitBtn.disabled = true;
    try {
      const payload = {
        id_pengguna: checked,
        kode_peran: roleCode,
        ...(note ? { catatan_pemberian: note } : {})
      };
      const res = await api('/admin/access-management/roles/bulk', payload, 'POST');
      modal.close();
      const countSuccess = res.berhasil?.length || 0;
      const countSkipped = res.dilewati?.length || 0;
      toast(`Peran massal berhasil ditetapkan ke ${countSuccess} akun. (${countSkipped} dilewati)`);
      accessManagementPage();
    } catch (err) {
      toast(err.message);
      submitBtn.disabled = false;
    }
  };
}
function openUserDetailModal(u, roles) {
  const isSelf = Number(u.id) === Number(user?.id);
  const activeRoleCodes = (u.peran || []).map(p => p.peran?.kode);
  const assignableRoles = roles.filter(r => {
    if (activeRoleCodes.includes(r.kode)) return false;
    if (r.kode === 'SUPER_ADMIN' && !user?.is_super_admin) return false;
    return true;
  });
  const assignableOptions = assignableRoles.map(r => `<option value="${esc(r.kode)}">${esc(r.nama)} (${esc(r.kode)})</option>`).join('');

  const modal = dialog(`
    <div>
      <div class="row between" style="align-items:flex-start">
        <div>
          <h2>${esc(u.nama_lengkap || 'Pengguna')}</h2>
          <small class="muted">${esc(u.email_login)}${u.username ? ` · @${esc(u.username)}` : ''}${u.nim ? ` · NIM ${esc(u.nim)}` : ''}</small>
        </div>
        <span class="badge ${u.status_akun?.kode === 'AKTIF' ? 'green' : 'yellow'}">${esc(u.status_akun?.nama || u.status_akun?.kode || '')}</span>
      </div>

      <section class="card" style="margin-top:14px">
        <div class="card-head"><h2>Profil Akun</h2></div>
        <form id="edit-profile-form" class="card-body">
          <label>Nama Lengkap
            <input name="nama_lengkap" type="text" maxlength="200" required value="${esc(u.nama_lengkap || '')}">
          </label>
          <div class="row between" style="margin-top:8px">
            <small class="muted">Versi profil: ${Number(u.versi_profil || 1)}</small>
            <button type="submit" class="small primary">${iconSvg('save')} <span>Simpan Nama</span></button>
          </div>
        </form>
      </section>

      <section class="card" style="margin-top:14px">
        <div class="card-head"><h2>Aksi Keamanan Akun</h2></div>
        <div class="card-body row" style="gap:8px;flex-wrap:wrap">
          ${u.status_akun?.kode === 'PENDING_AKTIVASI' ? `<button type="button" class="small" id="btn-send-activation">${iconSvg('send')} <span>Kirim Ulang Email Aktivasi</span></button>` : ''}
          ${user?.is_super_admin ? `<button type="button" class="small" id="btn-issue-token">${iconSvg('sparkles')} <span>Terbitkan Token Aktivasi</span></button>` : ''}
          ${user?.is_super_admin && u.status_akun?.kode === 'AKTIF' && !isSelf ? `<button type="button" class="small danger" id="btn-reset-password">${iconSvg('lock')} <span>Reset Password Akun</span></button>` : ''}
        </div>
      </section>

      <section class="card" style="margin-top:14px">
        <div class="card-head"><h2>Peran Aktif (${(u.peran || []).length})</h2></div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>PERAN</th>
                <th>PEMBERIAN</th>
                <th>CATATAN</th>
                <th>AKSI</th>
              </tr>
            </thead>
            <tbody>
              ${(u.peran || []).map(p => {
    const isLaboran = p.peran?.kode === 'LABORAN';
    return `<tr>
                  <td>
                    <strong>${esc(p.peran?.nama || p.peran?.kode)}</strong>
                    <br><small class="muted">Versi ${Number(p.versi)}</small>
                  </td>
                  <td><small>${esc(p.mulai_pada || '—')}</small></td>
                  <td><small>${esc(p.catatan_pemberian || '—')}</small></td>
                  <td>
                    <div class="row" style="gap:4px">
                      ${isLaboran ? `<button type="button" class="small btn-table-action" data-lab-scope="${Number(p.id)}">${iconSvg('map-pin')} <span>Cakupan Lab</span></button>` : ''}
                      ${!isSelf ? `<button type="button" class="small danger" data-end-role="${Number(p.id)}" data-role-version="${Number(p.versi)}">${iconSvg('trash')} <span>Akhiri</span></button>` : '<small class="muted">Akun sendiri</small>'}
                    </div>
                  </td>
                </tr>`;
  }).join('') || '<tr><td colspan="4" class="empty">Tidak ada peran aktif pada akun ini.</td></tr>'}
            </tbody>
          </table>
        </div>
      </section>

      ${!isSelf && u.dapat_diberi_peran && assignableRoles.length ? `
      <section class="card" style="margin-top:14px">
        <div class="card-head"><h2>Tetapkan Peran Baru</h2></div>
        <form id="assign-role-form" class="card-body">
          <label>Pilih Peran
            <select name="kode_peran" required>
              ${assignableOptions}
            </select>
          </label>
          <label>Alasan / Catatan Pemberian (minimal 10 karakter)
            <textarea name="catatan_pemberian" rows="2" minlength="10" maxlength="500" required placeholder="Tuliskan alasan penetapan peran ini…"></textarea>
          </label>
          <div class="row" style="margin-top:8px">
            <button type="submit" class="small primary">${iconSvg('user-plus')} <span>Tetapkan Peran</span></button>
          </div>
        </form>
      </section>
      `: ''}

      <div class="row" style="margin-top:18px">
        <button type="button" data-close>Tutup</button>
      </div>
    </div>
  `);

  const editProfileForm = modal.querySelector('#edit-profile-form');
  if (editProfileForm) {
    editProfileForm.onsubmit = async event => {
      event.preventDefault();
      const submitBtn = event.currentTarget.querySelector('[type="submit"]');
      submitBtn.disabled = true;
      const newName = editProfileForm.elements.nama_lengkap.value.trim();
      try {
        await api(`/admin/access-management/users/${Number(u.id)}/profile`, { nama_lengkap: newName, versi: Number(u.versi_profil || 1) }, 'PATCH');
        u.nama_lengkap = newName;
        u.versi_profil = Number(u.versi_profil || 1) + 1;
        modal.close();
        toast('Nama profil pengguna berhasil diperbarui.');
        accessManagementPage();
      } catch (err) {
        toast(err.message);
        submitBtn.disabled = false;
      }
    };
  }

  const btnSendActivation = modal.querySelector('#btn-send-activation');
  if (btnSendActivation) {
    btnSendActivation.onclick = async () => {
      btnSendActivation.disabled = true;
      try {
        const res = await api(`/admin/access-management/users/${Number(u.id)}/activation-email`, {}, 'POST');
        toast(res.message || 'Email aktivasi berhasil dikirim.');
      } catch (err) {
        toast(err.message);
      } finally {
        btnSendActivation.disabled = false;
      }
    };
  }

  const btnIssueToken = modal.querySelector('#btn-issue-token');
  if (btnIssueToken) {
    btnIssueToken.onclick = async () => {
      btnIssueToken.disabled = true;
      try {
        const res = await api(`/admin/access-management/users/${Number(u.id)}/activation-token`, {}, 'POST');
        const tokenVal = res.token || res.data?.token || '';
        const expVal = res.kadaluarsa_pada || res.data?.kadaluarsa_pada || '';
        dialog(`
          <div>
            <h2>Token Aktivasi Manual</h2>
            <p>Token berhasil diterbitkan untuk akun <strong>${esc(u.email_login)}</strong>.</p>
            <label>Token Aktivasi (salin sebelum ditutup)
              <input type="text" readonly value="${esc(tokenVal)}" style="font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:0.04em" onclick="this.select()">
            </label>
            <p class="help">Kedaluwarsa pada: ${esc(expVal)}</p>
            <div class="row actions modal-actions" style="justify-content:flex-end"><button type="button" data-close>Tutup</button></div>
          </div>
        `);
      } catch (err) {
        toast(err.message);
      } finally {
        btnIssueToken.disabled = false;
      }
    };
  }

  const btnResetPassword = modal.querySelector('#btn-reset-password');
  if (btnResetPassword) {
    btnResetPassword.onclick = () => {
      const passModal = dialog(`
        <form>
          <h2>Reset Password Akun</h2>
          <p>Password baru akan langsung diaktifkan untuk akun <strong>${esc(u.email_login)}</strong>. Seluruh sesi login lama akun ini akan dicabut.</p>
          <label>Password Baru (minimal 6 karakter)
            <input name="password" type="password" minlength="6" required autocomplete="new-password">
          </label>
          <label>Konfirmasi Password Baru
            <input name="password_confirmation" type="password" minlength="6" required autocomplete="new-password">
          </label>
          <div class="row">
            <button type="button" data-close>Batal</button>
            <button class="primary danger" type="submit">Ubah Password</button>
          </div>
        </form>
      `);
      passModal.querySelector('form').onsubmit = async event => {
        event.preventDefault();
        const p1 = passModal.querySelector('input[name="password"]').value;
        const p2 = passModal.querySelector('input[name="password_confirmation"]').value;
        if (p1 !== p2) {
          toast('Konfirmasi password tidak cocok.');
          return;
        }
        const submitBtn = event.currentTarget.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        try {
          await api(`/admin/access-management/users/${Number(u.id)}/password`, { password: p1, password_confirmation: p2 }, 'PATCH');
          passModal.close();
          toast('Password akun berhasil diperbarui. Semua sesi aktif akun tersebut telah dicabut.');
        } catch (err) {
          toast(err.message);
          submitBtn.disabled = false;
        }
      };
    };
  }

  modal.querySelectorAll('[data-end-role]').forEach(btn => {
    btn.onclick = () => {
      const assignmentId = Number(btn.dataset.endRole);
      const roleVersion = Number(btn.dataset.roleVersion);
      const endModal = dialog(`
        <form>
          <h2>Akhiri Peran Pengguna</h2>
          <p>Peran akan dinonaktifkan secara permanen dengan pencatatan audit.</p>
          <label>Alasan Pengakhiran Peran (minimal 10 karakter)
            <textarea name="catatan_berakhir" rows="3" minlength="10" maxlength="500" required placeholder="Tuliskan alasan pengakhiran peran ini…"></textarea>
          </label>
          <div class="row">
            <button type="button" data-close>Batal</button>
            <button class="small danger" type="submit">Konfirmasi Akhiri Peran</button>
          </div>
        </form>
      `);
      endModal.querySelector('form').onsubmit = async event => {
        event.preventDefault();
        const reason = endModal.querySelector('textarea[name="catatan_berakhir"]').value.trim();
        const submitBtn = event.currentTarget.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        try {
          await api(`/admin/access-management/users/${Number(u.id)}/roles/${assignmentId}/end`, { versi: roleVersion, catatan_berakhir: reason }, 'PATCH');
          endModal.close();
          modal.close();
          toast('Peran pengguna berhasil diakhiri.');
          accessManagementPage();
        } catch (err) {
          toast(err.message);
          submitBtn.disabled = false;
        }
      };
    };
  });

  modal.querySelectorAll('[data-lab-scope]').forEach(btn => {
    btn.onclick = () => {
      const assignmentId = Number(btn.dataset.labScope);
      const assignment = (u.peran || []).find(p => Number(p.id) === assignmentId);
      if (assignment) openLaboratoryScopesModal(u, assignment);
    };
  });

  const assignRoleForm = modal.querySelector('#assign-role-form');
  if (assignRoleForm) {
    assignRoleForm.onsubmit = async event => {
      event.preventDefault();
      const roleCode = assignRoleForm.elements.kode_peran.value;
      const note = assignRoleForm.elements.catatan_pemberian.value.trim();
      const submitBtn = event.currentTarget.querySelector('[type="submit"]');
      submitBtn.disabled = true;
      try {
        await api(`/admin/access-management/users/${Number(u.id)}/roles`, { kode_peran: roleCode, catatan_pemberian: note }, 'POST');
        modal.close();
        toast('Peran baru berhasil ditetapkan.');
        accessManagementPage();
      } catch (err) {
        toast(err.message);
        submitBtn.disabled = false;
      }
    };
  }
}
async function openLaboratoryScopesModal(u, assignment) {
  const modal = dialog(`
    <div>
      <h2>Cakupan Laboratorium · ${esc(u.nama_lengkap)}</h2>
      <p>Kelola akses laboratorium untuk peran <strong>${esc(assignment.peran?.nama || 'Laboran')}</strong>.</p>
      <div id="scopes-list-view"><div class="empty">Memuat cakupan laboratorium…</div></div>
      <div class="row" style="margin-top:14px">
        <button type="button" data-close>Tutup</button>
      </div>
    </div>
  `);
  const renderScopes = async () => {
    const listView = modal.querySelector('#scopes-list-view');
    try {
      const [scopes, refs] = await Promise.all([
        api(`/admin/access-management/users/${Number(u.id)}/roles/${Number(assignment.id)}/lab-scopes`),
        api('/references')
      ]);
      const labs = Array.isArray(refs.laboratorium) ? refs.laboratorium : [];
      const activeScopes = scopes.filter(s => s.status?.kode === 'AKTIF' && !s.berakhir_pada);
      const labOptions = labs.map(l => `<option value="${Number(l.id)}">${esc(l.nama)} (${esc(l.kode)})</option>`).join('');

      listView.innerHTML = `
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>JENIS CAKUPAN</th>
                <th>LABORATORIUM</th>
                <th>CATATAN</th>
                <th>AKSI</th>
              </tr>
            </thead>
            <tbody>
              ${activeScopes.map(s => `
                <tr>
                  <td><strong>${esc(s.jenis_cakupan?.nama || s.jenis_cakupan?.kode)}</strong></td>
                  <td>${s.laboratorium ? esc(s.laboratorium.nama) : '<span class="muted">Semua Laboratorium</span>'}</td>
                  <td><small>${esc(s.catatan_pemberian || '—')}</small></td>
                  <td>
                    <button class="small danger" data-end-scope="${Number(s.id)}" data-scope-version="${Number(s.versi)}">Akhiri</button>
                  </td>
                </tr>
              `).join('') || '<tr><td colspan="4" class="empty">Belum ada cakupan laboratorium aktif.</td></tr>'}
            </tbody>
          </table>
        </div>

        <section class="card" style="margin-top:14px">
          <div class="card-head"><h2>Tambah Cakupan Laboratorium</h2></div>
          <form id="add-scope-form" class="card-body">
            <label>Jenis Cakupan
              <select name="kode_jenis_cakupan" id="scope-type-select" required>
                <option value="SEMUA_LAB">Semua Laboratorium (SEMUA_LAB)</option>
                <option value="LABORATORIUM">Laboratorium Tertentu (LABORATORIUM)</option>
              </select>
            </label>
            <div id="scope-lab-field" style="display:none">
              <label>Pilih Laboratorium
                <select name="id_laboratorium">
                  ${labOptions}
                </select>
              </label>
            </div>
            <label>Catatan / Alasan Pemberian (minimal 10 karakter)
              <textarea name="catatan_pemberian" rows="2" minlength="10" maxlength="500" required placeholder="Alasan penetapan cakupan ini…"></textarea>
            </label>
            <div class="row" style="margin-top:8px">
              <button class="small primary" type="submit">Simpan Cakupan</button>
            </div>
          </form>
        </section>
      `;

      const typeSelect = listView.querySelector('#scope-type-select');
      const labField = listView.querySelector('#scope-lab-field');
      if (typeSelect && labField) {
        typeSelect.onchange = e => {
          labField.style.display = e.target.value === 'LABORATORIUM' ? 'block' : 'none';
        };
      }

      listView.querySelectorAll('[data-end-scope]').forEach(btn => {
        btn.onclick = () => {
          const scopeId = Number(btn.dataset.endScope);
          const scopeVer = Number(btn.dataset.scopeVersion);
          const endScopeModal = dialog(`
            <form>
              <h2>Akhiri Cakupan Laboratorium</h2>
              <label>Alasan Pengakhiran (minimal 10 karakter)
                <textarea name="catatan_berakhir" rows="3" minlength="10" maxlength="500" required placeholder="Tuliskan alasan pengakhiran cakupan…"></textarea>
              </label>
              <div class="row">
                <button type="button" data-close>Batal</button>
                <button class="small danger" type="submit">Akhiri Cakupan</button>
              </div>
            </form>
          `);
          endScopeModal.querySelector('form').onsubmit = async event => {
            event.preventDefault();
            const note = endScopeModal.querySelector('textarea[name="catatan_berakhir"]').value.trim();
            const submitBtn = event.currentTarget.querySelector('[type="submit"]');
            submitBtn.disabled = true;
            try {
              await api(`/admin/access-management/users/${Number(u.id)}/roles/${Number(assignment.id)}/lab-scopes/${scopeId}/end`, { versi: scopeVer, catatan_berakhir: note }, 'PATCH');
              endScopeModal.close();
              toast('Cakupan laboratorium berhasil diakhiri.');
              renderScopes();
            } catch (err) {
              toast(err.message);
              submitBtn.disabled = false;
            }
          };
        };
      });

      const addScopeForm = listView.querySelector('#add-scope-form');
      if (addScopeForm) {
        addScopeForm.onsubmit = async event => {
          event.preventDefault();
          const typeVal = addScopeForm.elements.kode_jenis_cakupan.value;
          const labVal = typeVal === 'LABORATORIUM' ? Number(addScopeForm.elements.id_laboratorium.value) : null;
          const note = addScopeForm.elements.catatan_pemberian.value.trim();
          const submitBtn = event.currentTarget.querySelector('[type="submit"]');
          submitBtn.disabled = true;
          try {
            await api(`/admin/access-management/users/${Number(u.id)}/roles/${Number(assignment.id)}/lab-scopes`, {
              kode_jenis_cakupan: typeVal,
              id_laboratorium: labVal,
              catatan_pemberian: note
            }, 'POST');
            toast('Cakupan laboratorium berhasil ditambahkan.');
            renderScopes();
          } catch (err) {
            toast(err.message);
            submitBtn.disabled = false;
          }
        };
      }
    } catch (err) {
      listView.innerHTML = `<div class="error" role="alert">${esc(err.message)}</div>`;
    }
  };
  renderScopes();
}
async function setComponents(values) {
  await api('/classes/' + current.class.id + '/components', { ...values, version: current.class.version });
  current = await api('/classes/' + current.class.id); classes = await api('/classes'); meeting = Math.min(meeting, current.class.meeting_count); renderClass(); toast('Jumlah pengisian diperbarui.');
}
function configureComponents() {
  if (hasUnsaved()) { toast('Simpan perubahan sebelum mengatur jumlah.'); return }
  const c = current.class, m = dialog(`<form><h2>Jumlah pengisian kelas ${esc(c.class)}</h2><p>Jumlah ini menjadi target kelengkapan sebelum diajukan ke dosen. Kolom yang sudah berisi data tidak dapat dihapus dengan mengurangi jumlah.</p><label>Pertemuan absensi (maksimal 14)<input type="number" name="meeting_count" min="1" max="14" value="${c.meeting_count}" required></label><label>Jumlah evaluasi (1–100)<input type="number" name="evaluation_count" min="1" max="100" value="${c.evaluation_count}" required></label><label>Jumlah asistensi (1–100)<input type="number" name="assistance_count" min="1" max="100" value="${c.assistance_count}" required></label><div class="row"><button type="button" data-close>Batal</button><button class="primary">Simpan jumlah</button></div></form>`);
  m.querySelector('form').onsubmit = async e => { e.preventDefault(); try { await setComponents(Object.fromEntries([...new FormData(e.target)].map(([k, v]) => [k, Number(v)]))); m.close() } catch (x) { toast(x.message) } };
}
async function addComponent() {
  if (hasUnsaved()) { toast('Simpan nilai sebelum menambah kolom.'); return }
  const c = current.class, values = { meeting_count: c.meeting_count, evaluation_count: c.evaluation_count, assistance_count: c.assistance_count };
  values[tab === 'Evaluasi' ? 'evaluation_count' : 'assistance_count']++;
  try { await setComponents(values) } catch (e) { toast(e.message) }
}
function importSchedule() {
  let base64 = null;
  const m = dialog(`<form><h2>Import jadwal praktikum</h2><p>Gunakan nama data master dan email akun yang sudah terdaftar. Satu kesalahan membatalkan seluruh import; jadwal lama tidak ditimpa.</p><a class="link" href="api.php?route=/admin/schedule-template" download>↓ Unduh template Excel (.xlsx)</a><small>Template memuat satu baris contoh. Ganti dengan jadwal yang akan diimport.</small><label>File jadwal (.xlsx, maksimal 4 MB)<input id="schedule-file" type="file" accept=".xlsx" required></label><div id="schedule-preview"></div><div class="row"><button type="button" data-close>Batal</button><button id="preview-schedule">Periksa file</button><button type="button" class="primary" id="commit-schedule" disabled>Import jadwal</button></div></form>`);
  const commit = m.querySelector('#commit-schedule'), file = m.querySelector('#schedule-file'), preview = m.querySelector('#schedule-preview');
  file.onchange = () => { base64 = null; commit.disabled = true; preview.textContent = '' };
  m.querySelector('form').onsubmit = async e => { e.preventDefault(); commit.disabled = true; base64 = null; const f = file.files[0]; if (!f || f.size > 4e6) { toast('Pilih file XLSX maksimal 4 MB.'); return } const reader = new FileReader(); reader.onload = async () => { try { const encoded = reader.result.split(',')[1], r = await api('/admin/import-schedule', { base64: encoded, preview: true }); preview.innerHTML = `<p>${r.rows.length} jadwal valid · ${r.errors.length} kesalahan</p>${r.errors.length ? '<div class="warning">' + r.errors.map(x => `Baris ${x.line}: ${esc(x.error)}`).join('<br>') + '</div>' : ''}<div class="table-wrap"><table><thead><tr><th>BARIS</th><th>MATA KULIAH / KELAS</th><th>FREKUENSI</th><th>HARI / JAM</th></tr></thead><tbody>${r.rows.map(x => `<tr><td>${x.line}</td><td>${esc(x.display['kode mk'])} / ${esc(x.display.kelas)}</td><td>${esc(x.values.frequency || '—')}</td><td>${esc(x.values.day)} ${esc(x.values.time)}</td></tr>`).join('')}</tbody></table></div>`; if (!r.errors.length && r.rows.length) { base64 = encoded; commit.disabled = false } } catch (x) { preview.textContent = x.message } }; reader.readAsDataURL(f) };
  commit.onclick = async () => { if (!base64) return; commit.disabled = true; try { const r = await api('/admin/import-schedule', { base64 }); m.close(); classes = await api('/classes'); if (user?.api_mode) { await apiClassList() } else { await masters() } toast(r.count + ' jadwal berhasil diimport. Gunakan Sinkron peserta untuk mengisi daftar mahasiswa.') } catch (e) { toast(e.message); commit.disabled = false } };
}
function createUser() {
  const available = masterData.mahasiswa.filter(m => !masterData.users.some(u => u.mahasiswa_id === m.id));
  const m = dialog(`<form><h2>Tambah akun pengguna</h2><label>Role<select name="role">${Object.entries(roleLabel).map(([k, v]) => `<option value="${k}">${v}</option>`).join('')}</select></label><label id="student-link" hidden>Mahasiswa / NIM<select name="mahasiswa_id"><option value="">Pilih mahasiswa…</option>${available.map(s => `<option value="${s.id}">${esc(s.nim)} · ${esc(s.name)}</option>`).join('')}</select><small>Satu mahasiswa hanya dapat dihubungkan ke satu akun.</small></label><label>Nama<input name="name" required></label><label>Email login<input name="email" type="email" required autocomplete="off"></label><label>Password awal (minimal 6 karakter)<input name="password" type="password" minlength="6" required autocomplete="new-password"></label><div class="row"><button type="button" data-close>Batal</button><button class="primary">Buat akun</button></div></form>`);
  const form = m.querySelector('form'), role = form.elements.role, student = form.elements.mahasiswa_id, name = form.elements.name;
  const sync = () => { const linked = role.value === 'mahasiswa'; m.querySelector('#student-link').hidden = !linked; student.required = linked; name.readOnly = linked; if (linked) name.value = available.find(s => s.id === Number(student.value))?.name || ''; };
  role.onchange = sync; student.onchange = sync; sync();
  form.onsubmit = async e => { e.preventDefault(); const values = Object.fromEntries(new FormData(form)); if (values.role === 'mahasiswa') values.mahasiswa_id = Number(values.mahasiswa_id); else delete values.mahasiswa_id; try { await api('/admin/users', values); m.close(); await masters(); toast('Akun pengguna berhasil dibuat.') } catch (x) { toast(x.message) } };
}
async function studentPortal() {
  $('#view').innerHTML = '<div class="empty">Memuat nilai Anda…</div>';
  try {
    const data = await api('/student/grades'); if (user?.role !== 'mahasiswa') return;
    if (!data.student) { $('#view').innerHTML = '<section class="card"><div class="card-body"><h1>Nilai Saya</h1><p>Akun belum terhubung ke data mahasiswa. Hubungi pengelola laboratorium untuk memeriksa hubungan akun dengan NIM Anda.</p></div></section>'; return }
    const s = data.student, courses = data.classes;
    $('#view').innerHTML = `<div class="intro"><h1>Nilai Saya</h1><p>${esc(s.name)} · ${esc(s.nim)} · ${esc(s.prodi)}</p></div><div class="student-summary"><section class="stat"><span class="label">Kelas praktikum saya</span><b>${courses.length}</b><small>Seluruh kelas yang Anda ikuti</small></section><section class="stat"><span class="label">Penilaian terverifikasi</span><b>${courses.filter(c => !c.provisional).length}</b><small>Disetujui dosen atau dikunci</small></section><section class="stat"><span class="label">Penilaian sementara</span><b>${courses.filter(c => c.provisional).length}</b><small>Masih dalam proses pengisian/verifikasi</small></section></div><div class="filters"><input id="student-search" placeholder="Cari mata kuliah atau kelas…"><select id="student-year"><option value="">Semua tahun akademik</option>${[...new Set(courses.map(c => c.year))].map(y => `<option>${esc(y)}</option>`).join('')}</select><select id="student-semester"><option value="">Semua semester</option>${[...new Set(courses.map(c => c.semester))].map(y => `<option>${esc(y)}</option>`).join('')}</select></div><div id="my-grades"></div><p class="help">Nilai sementara masih dapat berubah. Jika ada nilai yang perlu diperiksa, hubungi asisten atau dosen pengampu kelas.</p>`;
    const update = () => {
      const q = $('#student-search').value.toLowerCase(); const filtered = courses.filter(c => (c.course + ' ' + c.code + ' ' + c.class).toLowerCase().includes(q) && (!$('#student-year').value || c.year === $('#student-year').value) && (!$('#student-semester').value || c.semester === $('#student-semester').value));
      $('#my-grades').innerHTML = filtered.map(c => `<section class="card"><div class="card-head row between"><div><h2>${esc(c.course)}</h2><p>${esc(c.code)} · Frekuensi ${esc(c.class)} · ${esc(c.semester)} ${esc(c.year)}</p></div><div class="row">${badge(c.state)}${c.provisional ? '<span class="badge yellow">Nilai sementara</span>' : ''}</div></div><div class="card-body"><p>${esc(c.day)}, ${esc(c.time)} · ${esc(c.lab)}<br>Dosen: ${esc(c.lecturer)}</p><div class="grade-summary">${[['Evaluasi', c.ev], ['Asistensi', c.as], ['MID', c.mid], ['UAS', c.uas], ['Nilai akhir', c.na]].map(([l, v]) => `<div><small>${l}</small><strong>${num(v)}</strong></div>`).join('')}<div><small>Huruf</small><strong>${esc(c.letter)}</strong></div></div><div class="row between" style="margin-top:22px"><small>Kehadiran: ${c.presence === null ? 'Belum ada pertemuan' : num(c.presence) + '%'} · ${c.meetings.length}/${c.meeting_count} pertemuan tercatat</small><button class="primary small" data-my-course="${c.id}">Lihat rincian nilai</button></div></div></section>`).join('') || '<section class="card"><div class="empty">' + (courses.length ? 'Tidak ada kelas sesuai filter.' : 'Anda belum terdaftar sebagai peserta praktikum. Hubungi pengelola laboratorium.') + '</div></section>';
      document.querySelectorAll('[data-my-course]').forEach(b => b.onclick = () => myGradeDetail(courses.find(c => c.id === Number(b.dataset.myCourse))));
    }; $('.filters').oninput = update; update();
  } catch (e) { if (user?.role === 'mahasiswa') $('#view').innerHTML = `<div class="error">${esc(e.message)}</div>` }
}
function myGradeDetail(c) {
  const component = (title, count, items) => `<section><h3>${title}</h3><div class="grade-chips">${Array.from({ length: count }, (_, i) => `<div><small>${title} ${i + 1}</small><strong>${num(items.find(x => x.number === i + 1)?.value)}</strong></div>`).join('')}</div></section>`;
  dialog(`<div class="row between"><h2>${esc(c.course)}</h2><button data-close>Tutup</button></div><p>Frekuensi ${esc(c.class)} · ${esc(c.year)} ${esc(c.semester)}</p>${c.provisional ? '<div class="warning">Nilai sementara — belum diverifikasi dosen.</div>' : ''}${component('Evaluasi', c.evaluation_count, c.evaluations)}${component('Asistensi', c.assistance_count, c.assistance)}<section style="margin:22px 0"><h3>Ujian & nilai akhir</h3><p>MID: ${num(c.mid)} · UAS: ${num(c.uas)} · Nilai akhir: ${num(c.na)} (${esc(c.letter)})</p><small>Evaluasi 20% + Asistensi 20% + MID 30% + UAS 30%. Tanda — berarti belum dinilai.</small></section><h3>Kehadiran per pertemuan</h3><div class="table-wrap"><table><thead><tr><th>PERTEMUAN</th><th>TANGGAL</th><th>MATERI</th><th>KEHADIRAN</th></tr></thead><tbody>${Array.from({ length: c.meeting_count }, (_, i) => { const p = c.meetings.find(x => x.number === i + 1); return `<tr><td>${i + 1}</td><td>${esc(p?.date || '—')}</td><td style="white-space:normal;min-width:150px">${esc(p?.material || 'Belum tercatat')}</td><td>${esc(({ H: 'Hadir', I: 'Izin', S: 'Sakit', A: 'Alpa', T: 'Terlambat' })[p?.value] || 'Belum diisi')}</td></tr>` }).join('')}</tbody></table></div>`);
}
window.addEventListener('beforeunload', e => { if (hasUnsaved()) { e.preventDefault(); e.returnValue = '' } }); init();
function frequencyMaster() {
  $('#view').innerHTML = `<div class="intro"><h1>Master Frekuensi Praktikum</h1><p>Satu NIM dapat mengikuti beberapa frekuensi. Kelas adalah informasi peserta.</p></div><div class="master-tabs">${Object.entries(masterLabels).map(([k, l]) => `<button data-master="${k}" class="${k === masterTab ? 'active' : ''}">${l}</button>`).join('')}</div><section class="card"><div class="card-head row between"><h2>Peserta per frekuensi</h2><div class="row"><button id="frequency-add">+ Tambah peserta</button><button id="frequency-import">↑ Import Excel</button><a href="${apiUrl('/admin/frequency-template')}">Unduh template</a></div></div><div class="card-body"><input id="frequency-search" placeholder="Cari NIM, nama, kelas, frekuensi"></div><div id="frequency-rows"></div></section>`;
  document.querySelectorAll('[data-master]').forEach(b => b.onclick = () => { masterTab = b.dataset.master; masters() });
  const draw = () => { const q = $('#frequency-search').value.toLowerCase(), list = masterData.anggota_frekuensi.filter(r => [r.nim, r.nama, r.kelas, r.frekuensi].join(' ').toLowerCase().includes(q)); $('#frequency-rows').innerHTML = `<div class="table-wrap"><table><thead><tr><th>No</th><th>NIM</th><th>Nama</th><th>Kelas</th><th>Frekuensi</th><th>Aksi</th></tr></thead><tbody>${list.map((r, i) => `<tr><td>${i + 1}</td>${['nim', 'nama', 'kelas', 'frekuensi'].map(k => `<td>${esc(r[k])}</td>`).join('')}<td><button type="button" data-transfer="${r.id}">Pindah frekuensi</button></td></tr>`).join('')}</tbody></table></div>${list.length ? '' : '<div class="empty">Belum ada peserta yang sesuai.</div>'}`; document.querySelectorAll('[data-transfer]').forEach(b => b.onclick = () => transferForm(Number(b.dataset.transfer))); }; $('#frequency-search').oninput = draw; draw();
  $('#frequency-add').onclick = () => frequencyForm(false); $('#frequency-import').onclick = () => frequencyForm(true);
}
function frequencyForm(importing) {
  const m = dialog(`<form><h2>${importing ? 'Import' : 'Tambah'} peserta frekuensi</h2><p>${importing ? 'Header XLSX: nim, nama, kelas, frekuensi. Maksimal 4 MB / 2.000 baris. Satu kesalahan membatalkan seluruh file.' : 'NIM yang sudah ada dapat ditambahkan ke frekuensi lain dengan nama yang sama.'}</p><label>Program studi<select name="prodi_id">${masterData.prodi.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></label>${importing ? '<label>File XLSX<input name="file" type="file" accept=".xlsx" required></label>' : ['nim', 'nama', 'kelas', 'frekuensi'].map(k => `<label>${k.toUpperCase()}<input name="${k}" maxlength="100" required></label>`).join('')}<p>Frekuensi baru dibuat otomatis. Sesudah selesai, sinkron peserta pada jadwal frekuensi terkait.</p><div class="row"><button type="button" data-close>Batal</button><button type="submit" class="primary">Simpan</button></div></form>`);
  m.querySelector('form').onsubmit = async e => { e.preventDefault(); const button = e.target.querySelector('[type=submit]'); button.disabled = true; try { const data = Object.fromEntries(new FormData(e.target)); data.prodi_id = Number(data.prodi_id); if (importing) { if (data.file.size > 4e6) throw Error('Maksimal 4 MB.'); data.base64 = await new Promise((resolve, reject) => { const r = new FileReader(); r.onload = () => resolve(r.result.split(',')[1]); r.onerror = () => reject(Error('File tidak dapat dibaca.')); r.readAsDataURL(data.file) }); delete data.file; } const result = await api('/admin/' + (importing ? 'frequency-import' : 'frequency-member'), data); m.close(); await masters(); toast(result.count + ' peserta ditambahkan.') } catch (x) { toast(x.message) } finally { button.disabled = false } };
}


function periodSummary() {
  const p = activePeriod, ready = p && p.tahun_id && p.semester_id;
  const date = v => v ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(v + 'T12:00:00')) : '—';
  return `<section class="period-summary" aria-label="Periode semester aktif">${[['Semester aktif', ready ? p.semester : 'Belum diatur'], ['Tahun akademik', ready ? p.academic_year : 'Belum diatur'], ['Periode pelaksanaan praktikum', ready ? date(p.start_date) + ' – ' + date(p.end_date) : 'Belum diatur']].map(([label, value]) => `<div><small>${label}</small><strong>${esc(value)}</strong></div>`).join('')}</section>`;
}
function periodMaster() {
  const p = activePeriod || {};
  $('#view').innerHTML = `<div class="intro"><h1>Periode Semester Aktif</h1></div><div class="master-tabs">${Object.entries(masterLabels).map(([k, l]) => `<button data-master="${k}" class="${k === masterTab ? 'active' : ''}">${l}</button>`).join('')}</div><section class="card"><div class="card-body"><form id="period-form"><div class="form-grid">${[['tahun_id', 'Tahun akademik', 'tahun_akademik'], ['semester_id', 'Semester aktif', 'semester']].map(([key, label, table]) => `<label>${label}<select name="${key}" required><option value="">Pilih ${label.toLowerCase()}</option>${masterData[table].map(r => `<option value="${r.id}" ${Number(p[key]) === r.id ? 'selected' : ''}>${esc(r.name)}</option>`).join('')}</select></label>`).join('')}<label>Tanggal mulai<input name="start_date" type="date" value="${esc(p.start_date || '')}" required></label><label>Tanggal selesai<input name="end_date" type="date" value="${esc(p.end_date || '')}" required></label></div><p>Hanya satu periode aktif. Data jadwal dan nilai periode sebelumnya tetap tersedia.</p><button type="submit" class="primary">Simpan periode aktif</button></form></div></section>`;
  document.querySelectorAll('[data-master]').forEach(b => b.onclick = () => { masterTab = b.dataset.master; masters() });
  $('#period-form').onsubmit = async e => { e.preventDefault(); const button = e.target.querySelector('button[type=submit]'); button.disabled = true; try { const v = Object.fromEntries(new FormData(e.target)); v.tahun_id = Number(v.tahun_id); v.semester_id = Number(v.semester_id); v.version = p.version || 0; await api('/active-period', v); activePeriod = await api('/active-period'); $('#period-summary').innerHTML = periodSummary(); periodMaster(); toast('Periode semester aktif diperbarui.') } catch (x) { toast(x.message) } finally { button.disabled = false } };
}

function importUsers() {
  const m = dialog(`<form><h2>Import Akun Pengguna</h2><p>Header: nama, email, role, password, nim. Maksimal 100 akun / 4 MB. Role: admin, dosen, asisten, mahasiswa. Password 6–72 byte. NIM wajib untuk mahasiswa dan harus sudah terdaftar; kosongkan untuk role lain.</p><a href="${apiUrl('/admin/users-template')}">↓ Unduh template Excel</a><label style="margin-top:20px">File XLSX<input type="file" accept=".xlsx" id="users-file" required></label><p>Ganti data contoh dan password pada template. Password tidak ditampilkan pada pratinjau. Satu kesalahan membatalkan seluruh import.</p><div class="row"><button type="button" data-close>Batal</button><button type="submit">Periksa file</button><button type="button" class="primary" id="users-commit" disabled>Import akun</button></div><div id="users-preview" style="margin-top:20px" role="status"></div></form>`);
  const file = m.querySelector('#users-file'), commit = m.querySelector('#users-commit'), preview = m.querySelector('#users-preview'); let encoded = null;
  file.onchange = () => { encoded = null; commit.disabled = true; preview.textContent = '' };
  m.querySelector('form').onsubmit = async e => { e.preventDefault(); encoded = null; commit.disabled = true; const button = e.target.querySelector('[type=submit]'); button.disabled = true; file.disabled = true; try { const f = file.files[0]; if (!f || f.size > 4e6) throw Error('Pilih file XLSX maksimal 4 MB.'); const data = await new Promise((resolve, reject) => { const r = new FileReader(); r.onload = () => resolve(r.result.split(',')[1]); r.onerror = () => reject(Error('File tidak dapat dibaca.')); r.readAsDataURL(f) }); const result = await api('/admin/import-users', { base64: data, preview: true }); preview.innerHTML = `<p>${result.rows.length} akun valid · ${result.errors.length} kesalahan</p>${result.errors.map(r => `<div class="error">Baris ${r.line}: ${esc(r.error)}</div>`).join('')}<div class="table-wrap"><table><thead><tr><th>Baris</th><th>Nama</th><th>Email</th><th>Role</th><th>NIM</th></tr></thead><tbody>${result.rows.map(r => `<tr><td>${r.line}</td>${['nama', 'email', 'role', 'nim'].map(k => `<td>${esc(r[k])}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`; if (!result.errors.length && result.rows.length) { encoded = data; commit.disabled = false } } catch (x) { preview.textContent = x.message } finally { button.disabled = false; file.disabled = false } };
  commit.onclick = async () => { if (!encoded) return; commit.disabled = true; file.disabled = true; m.querySelector('[type=submit]').disabled = true; try { const r = await api('/admin/import-users', { base64: encoded }); encoded = null; m.close(); await masters(); toast(r.count + ' akun berhasil diimport.') } catch (x) { preview.textContent = x.message; encoded = null } finally { file.disabled = false; m.querySelector('[type=submit]').disabled = false } };
  m.addEventListener('close', () => { encoded = null; file.value = '' });
}

function masterSearch() {
  const area = $('#master-content'), rows = [...area.querySelectorAll('tbody tr')]; if (!area.querySelector('table')) return;
  const box = document.createElement('div'); box.className = 'card-body'; box.innerHTML = '<label>Cari data<input type="search" placeholder="Ketik kata kunci…"></label><small role="status"></small>'; area.prepend(box);
  const input = box.querySelector('input'), status = box.querySelector('small'); const apply = () => { let n = 0; for (const row of rows) { const match = [...row.cells].slice(1, -1).some(cell => cell.textContent.toLowerCase().includes(input.value.trim().toLowerCase())); row.hidden = !match; if (match) row.cells[0].textContent = ++n; } status.textContent = n + ' dari ' + rows.length + ' data' + (n ? '' : ' — Tidak ada hasil'); }; input.oninput = apply; apply();
}
async function transferForm(id) {
  try {
    classes = await api('/classes'); const member = masterData.anggota_frekuensi.find(r => r.id === id); const source = classes.filter(c => c.frequency === member.frekuensi && c.active && ['Draft', 'Perlu Revisi'].includes(c.state));
    const label = c => `${c.course} · ${c.frequency} · ${c.year} ${c.semester}`;
    const m = dialog(`<form><h2>Pindah frekuensi mahasiswa</h2><p>${esc(member.nim)} · ${esc(member.nama)}</p><label>Jadwal asal<select name="source_id" required><option value="">Pilih jadwal</option>${source.map(c => `<option value="${c.id}">${esc(label(c))}</option>`).join('')}</select></label><label>Jadwal tujuan<select name="target_id" required></select></label><label>Alasan<textarea name="reason" required maxlength="1000"></textarea></label><p>Peserta di jadwal asal dinonaktifkan. Nilai dan absensi lama tetap tersimpan sebagai arsip, tidak disalin ke tujuan. Jadwal terverifikasi/dikunci perlu dibuka kembali oleh admin.</p><div class="row"><button type="button" data-close>Batal</button><button class="primary" type="submit">Pindahkan mahasiswa</button></div></form>`);
    const from = m.querySelector('[name=source_id]'), to = m.querySelector('[name=target_id]'); from.onchange = () => { const a = source.find(c => c.id === Number(from.value)); to.innerHTML = '<option value="">Pilih frekuensi tujuan</option>' + classes.filter(b => a && b.frequency !== a.frequency && b.mk_id === a.mk_id && b.tahun_id === a.tahun_id && b.semester_id === a.semester_id && b.prodi_id === a.prodi_id && b.active && ['Draft', 'Perlu Revisi'].includes(b.state)).map(c => `<option value="${c.id}">${esc(label(c))}</option>`).join(''); }; from.onchange();
    m.querySelector('form').onsubmit = async e => { e.preventDefault(); const button = e.target.querySelector('[type=submit]'); button.disabled = true; try { const v = Object.fromEntries(new FormData(e.target)); v.member_id = id; v.source_id = Number(v.source_id); v.target_id = Number(v.target_id); await api('/admin/transfer-frequency', v); m.close(); classes = await api('/classes'); await masters(); toast('Mahasiswa berhasil dipindahkan.') } catch (x) { toast(x.message) } finally { button.disabled = false } };
  } catch (x) { toast(x.message) }
}

function apiWorkflowActions(c, s) {
  const status = s.status_workflow || 'DRAF', targets = [];
  if (['admin', 'asisten'].includes(user.role) && ['DRAF', 'PERLU_REVISI'].includes(status)) targets.push(['DIAJUKAN', 'Ajukan ke dosen', false]);
  if (user.role === 'dosen' && status === 'DIAJUKAN') { targets.push(['PERLU_REVISI', 'Minta revisi', true], ['TERVERIFIKASI', 'Verifikasi', false]) }
  if (user.role === 'dosen' && status === 'TERVERIFIKASI') targets.push(['DIKUNCI', 'Kunci penilaian', false]);
  if (user.role === 'admin' && ['DIAJUKAN', 'TERVERIFIKASI', 'DIKUNCI'].includes(status)) targets.push(['DRAF', 'Reset ke Draf', true]);
  return targets.map(([target, label, needsNote]) => ({ target, label, needsNote }));
}
function apiWorkflowIndicator(schedule, role) {
  const status = String(schedule.status_workflow || 'DRAF').toUpperCase();
  const definitions = {
    DRAF: { tone: 'draft', label: 'Belum diajukan', description: 'Menunggu Asisten mengajukan hasil.' },
    DIAJUKAN: { tone: 'submitted', label: 'Menunggu tinjauan Dosen', description: 'Pengajuan sudah diterima dan menunggu pemeriksaan Dosen.' },
    PERLU_REVISI: { tone: 'revision', label: role === 'asisten' ? 'Dosen meminta revisi' : 'Perlu revisi', description: role === 'asisten' ? 'Perbaiki hasil penilaian sesuai catatan, lalu ajukan kembali.' : 'Pengajuan dikembalikan kepada Asisten untuk diperbaiki.' },
    TERVERIFIKASI: { tone: 'verified', label: 'Disetujui Dosen', description: 'Hasil penilaian sudah diverifikasi Dosen.' },
    DIKUNCI: { tone: 'locked', label: 'Penilaian dikunci', description: 'Hasil sudah dikunci dan tidak dapat diubah.' }
  };
  const state = definitions[status] || { tone: 'draft', label: apiStatus(status), description: '' };
  const note = String(schedule.catatan_workflow || '').trim();
  const revision = status === 'PERLU_REVISI'
    ? `<div class="workflow-feedback workflow-feedback-revision" role="status"><strong>${role === 'asisten' ? 'Catatan revisi Dosen' : 'Catatan pengajuan'}</strong><span>${esc(note || state.description)}</span>${role === 'asisten' ? '<small>Pengajuan perlu diperbaiki sebelum dapat diajukan kembali.</small>' : ''}</div>`
    : (status === 'DIAJUKAN' && note ? `<div class="workflow-feedback workflow-feedback-note" role="note"><strong>Catatan pengajuan</strong><span>${esc(note)}</span></div>` : '');
  return `<div class="workflow-indicator"><span class="workflow-status workflow-status-${state.tone}" role="status"><span class="workflow-status-dot" aria-hidden="true"></span>${esc(state.label)}</span><span class="workflow-description">${esc(state.description)}</span></div>${revision}`;
}
async function apiClassForm(c) {
  try {
    const refs = await api('/references');
    if (!Array.isArray(refs.periode_akademik) || !Array.isArray(refs.program_studi) || !Array.isArray(refs.mata_kuliah)) throw Error('Referensi periode, program studi, atau mata kuliah belum tersedia.');
    const g = c?.kelompok || {}, groupId = Number(c?.id_kelas_kuliah || 0), modal = dialog(`<form><h2>${c ? 'Edit kelompok Praktikum' : 'Buat kelas Praktikum'}</h2>${c ? '' : `<label>Periode akademik<select name="id_periode_akademik" required>${refs.periode_akademik.map(x => `<option value="${Number(x.id)}" ${x.aktif ? 'selected' : ''}>${esc(x.tahun_akademik)} · ${esc(x.nama_semester)}</option>`).join('')}</select></label><div class="form-grid"><label>Program studi<select name="id_program_studi" required>${refs.program_studi.map(x => `<option value="${Number(x.id)}">${esc(x.kode)} · ${esc(x.nama)}</option>`).join('')}</select></label><label>Mata kuliah<select name="id_mata_kuliah" required>${refs.mata_kuliah.map(x => `<option value="${Number(x.id)}">${esc(x.kode)} · ${esc(x.nama)}</option>`).join('')}</select></label></div>`}<div class="form-grid"><label>Kode kelompok<input name="kode_kelompok" maxlength="100" value="${esc(g.kode || '')}" placeholder="TI_ALPRO-1" pattern="[A-Za-z0-9]+_[A-Za-z0-9]+-[1-9][0-9]*" required></label><label>Nama kelompok<input name="nama_kelompok" maxlength="150" value="${esc(g.nama || '')}" required></label>${c ? '' : `<label>Jumlah pertemuan rencana<input name="jumlah_pertemuan_rencana" type="number" min="1" max="14" value="14" required></label>`}</div><label>Catatan<textarea name="catatan" maxlength="5000">${esc(c?.catatan || '')}</textarea></label><p class="help">Kode frekuensi adalah identitas canonical, misalnya <code>TI_ALPRO-1</code>. Nama kelompok adalah label tampilan; sinkronisasi Sheet berikutnya akan mengikuti kolom Kelas pada Sheet.</p><div class="row"><button type="button" data-close>Batal</button><button class="primary" type="submit">Simpan</button></div></form>`);
    modal.querySelector('form').onsubmit = async event => { event.preventDefault(); const button = event.target.querySelector('[type=submit]'); button.disabled = true; try { const values = Object.fromEntries(new FormData(event.target)); values.kode_kelompok = values.kode_kelompok.trim().toUpperCase(); if (values.jumlah_pertemuan_rencana !== undefined) values.jumlah_pertemuan_rencana = Number(values.jumlah_pertemuan_rencana); if (c) { values.versi = Number(g.versi); const nameOnly = values.kode_kelompok === String(g.kode || '').toUpperCase() && values.catatan === String(c.catatan || ''); const payload = nameOnly ? { versi: values.versi, nama_kelompok: values.nama_kelompok } : values; await api(`/classes/${groupId}`, payload, 'PATCH') } else { for (const key of ['id_periode_akademik', 'id_program_studi', 'id_mata_kuliah']) values[key] = Number(values[key]); await api('/classes', values, 'POST') } modal.close(); toast(c ? 'Kelompok diperbarui di API pusat.' : 'Kelas Praktikum dibuat di API pusat.'); classes = await api('/classes'); if (c) await apiClassDetailWithEntry(groupId); else apiClassList() } catch (error) { toast(error.message); button.disabled = false } };
  } catch (error) { toast(error.message) }
}
async function apiGradeComponentsForm(c) {
  const components = c.komponen_nilai || [], count = prefix => components.filter(x => x.aktif && String(x.kode || '').startsWith(prefix + '_')).length, sessions = Array.isArray(c.sesi) ? c.sesi : [], latestMeeting = sessions.reduce((latest, item) => Math.max(latest, Number(item.nomor_pertemuan) || 0), 0), minimum = Math.max(1, latestMeeting), meetingCount = Math.min(14, Math.max(minimum, Number(c.jumlah_pertemuan_rencana) || 14));
  const modal = dialog(`<form><h2>Jumlah pengisian kelas ${esc(c.kelompok.kode)}</h2><p>Atur target absensi, evaluasi, dan asistensi sebelum hasil diajukan ke dosen.</p><div class="form-grid"><label>Pertemuan absensi (maksimal 14)<input name="jumlah_pertemuan_rencana" type="number" min="${minimum}" max="14" value="${meetingCount}" required></label><label>Jumlah evaluasi<input name="jumlah_evaluasi" type="number" min="1" max="100" value="${Math.max(1, count('EVALUASI'))}" required></label><label>Jumlah asistensi<input name="jumlah_asistensi" type="number" min="1" max="100" value="${Math.max(1, count('ASISTENSI'))}" required></label></div><p class="help">${latestMeeting ? `Pertemuan sampai nomor ${latestMeeting} sudah tercatat; target tidak dapat dikurangi di bawahnya.` : 'Sesi yang sudah tersimpan tetap aman saat target diubah.'} Jumlah evaluasi/asistensi tidak dapat dikurangi jika komponen terkait sudah memiliki nilai. Perubahan disimpan sekaligus.</p><div class="row"><button type="button" data-close>Batal</button><button class="primary" type="submit">Simpan jumlah</button></div></form>`);
  modal.querySelector('form').onsubmit = async event => { event.preventDefault(); const button = event.target.querySelector('[type=submit]'); button.disabled = true; try { const v = Object.fromEntries(new FormData(event.target)); await api(`/classes/${Number(c.id_kelas_kuliah)}/components`, { versi: Number(c.kelompok.versi), jumlah_pertemuan_rencana: Number(v.jumlah_pertemuan_rencana), jumlah_evaluasi: Number(v.jumlah_evaluasi), jumlah_asistensi: Number(v.jumlah_asistensi) }, 'PUT'); modal.close(); toast('Target absensi, evaluasi, dan asistensi tersimpan sekaligus.'); await apiClassDetailWithEntry(Number(c.id_kelas_kuliah)) } catch (error) { toast(error.message); button.disabled = false } };
}
async function apiWorkflowChange(c, s, target, needsNote) {
  let note = null;
  if (needsNote) { note = window.prompt(target === 'DRAF' ? 'Alasan membuka kembali ke Draf:' : 'Catatan untuk pengembalian revisi:'); if (note === null) return; if (!note.trim()) { toast('Catatan wajib diisi.'); return } }
  if (target === 'DIAJUKAN' && !window.confirm('Ajukan jadwal ini ke dosen untuk diverifikasi?')) return;
  try { await api(`/classes/${Number(c.id_kelas_kuliah)}/schedules/${Number(s.id)}/workflow`, { versi: Number(c.kelompok.versi), versi_workflow: Number(s.versi_workflow || 0), status: target, catatan: note }, 'PATCH'); toast('Status workflow diperbarui di API pusat.'); await apiClassDetailWithEntry(Number(c.id_kelas_kuliah)) } catch (e) { toast(e.message) }
}
async function apiScheduleForm(c, schedule, examType) {
  try {
    const refs = (await api('/references')) || {};
    const d = schedule || {};
    const schedulesList = c.jadwal || [];
    const regularSchedule = schedulesList.find(s => s.pola_perulangan === 'MINGGUAN' && !String(s.catatan_workflow || '').includes('UJIAN_')) || schedulesList[0] || null;

    // Resilient fallback jika backend hanya mengembalikan data parsial untuk akun tertentu
    if (!Array.isArray(refs.laboratorium) || refs.laboratorium.length === 0) {
      const extractedLab = [];
      const seenLab = new Set();
      schedulesList.forEach(j => {
        if (j.id_laboratorium && !seenLab.has(j.id_laboratorium)) {
          seenLab.add(j.id_laboratorium);
          extractedLab.push({ id: j.id_laboratorium, kode: j.kode_laboratorium || 'LAB', nama: j.nama_laboratorium || 'Laboratorium' });
        }
      });
      if (extractedLab.length > 0) refs.laboratorium = extractedLab;
    }

    if (!Array.isArray(refs.dosen) || refs.dosen.length === 0) {
      const extractedDosen = [];
      const seenDosen = new Set();
      schedulesList.forEach(j => {
        (j.pengajar || []).forEach(p => {
          if (p.peran === 'DOSEN_PENGAMPU' && p.id_user && !seenDosen.has(p.id_user)) {
            seenDosen.add(p.id_user);
            extractedDosen.push({ id_user: p.id_user, nama: p.nama || p.label || 'Dosen Pengampu' });
          }
        });
      });
      if (extractedDosen.length > 0) refs.dosen = extractedDosen;
    }

    if (!Array.isArray(refs.asisten) || refs.asisten.length === 0) {
      const extractedAsisten = [];
      const seenAsisten = new Set();
      schedulesList.forEach(j => {
        (j.pengajar || []).forEach(p => {
          if (p.id_user && !seenAsisten.has(p.id_user) && p.peran !== 'DOSEN_PENGAMPU') {
            seenAsisten.add(p.id_user);
            extractedAsisten.push({ id_user: p.id_user, nama: p.nama || p.label || 'Asisten', kode_tingkat_asisten: p.peran });
          }
        });
      });
      if (extractedAsisten.length > 0) refs.asisten = extractedAsisten;
    }

    if (!Array.isArray(refs.laboratorium) || !Array.isArray(refs.dosen) || !Array.isArray(refs.asisten)) {
      throw Error('Daftar laboratorium, dosen, atau asisten tidak tersedia untuk akun ini.');
    }

    const existingExamTag = String(d.catatan_workflow || '').includes('MID') ? 'MID' : (String(d.catatan_workflow || '').includes('UAS') ? 'UAS' : null);
    const effectiveExamType = examType || existingExamTag || null;
    const isExam = !!effectiveExamType;

    // Untuk ujian baru (!schedule && isExam), otomatis gunakan konfigurasi awal dari jadwal reguler jika tersedia
    if (!schedule && isExam && regularSchedule) {
      if (!d.id_laboratorium && regularSchedule.id_laboratorium) d.id_laboratorium = regularSchedule.id_laboratorium;
      if (!d.hari_ke && regularSchedule.hari_ke) d.hari_ke = regularSchedule.hari_ke;
      if (!d.jam_mulai && regularSchedule.jam_mulai) d.jam_mulai = regularSchedule.jam_mulai;
      if (!d.jam_selesai && regularSchedule.jam_selesai) d.jam_selesai = regularSchedule.jam_selesai;
      if (!d.pola_perulangan) d.pola_perulangan = 'SEKALI';
    }

    const teachers = (d && Array.isArray(d.pengajar) && d.pengajar.length) ? d.pengajar : (regularSchedule?.pengajar || []);
    const selected = role => teachers.find(x => x.peran === role)?.id_user || '';
    const dateStart = d.tanggal_mulai || activePeriod?.start_date || '', dateEnd = d.tanggal_selesai || activePeriod?.end_date || '';

    const badgeColorClass = effectiveExamType === 'MID' ? 'is-amber' : (effectiveExamType === 'UAS' ? 'is-purple' : 'is-blue');
    const badgeIcon = effectiveExamType === 'MID' ? 'MID' : (effectiveExamType === 'UAS' ? 'UAS' : 'REG');
    const modalTitle = schedule
      ? (isExam ? `Edit Jadwal Ujian ${effectiveExamType}` : 'Edit Jadwal Praktikum')
      : (isExam ? `Atur Jadwal Ujian ${effectiveExamType}` : 'Tambah Jadwal Praktikum');
    const modalSubtitle = `${esc(c.mata_kuliah?.nama || 'Praktikum')} · Frekuensi ${esc(c.kelompok?.kode || '')} · ${esc(c.program_studi?.nama || '')}`;

    const examPresetHtml = isExam ? `
      <div class="exam-mode-cards" role="radiogroup" aria-label="Mode Pengaturan Jadwal Ujian">
        <button type="button" class="exam-mode-card ${regularSchedule ? 'is-active' : 'is-disabled'}" id="api-exam-use-regular" ${regularSchedule ? '' : 'disabled'} role="radio" aria-checked="${regularSchedule ? 'true' : 'false'}">
          <div class="exam-mode-radio">
            <div class="exam-radio-dot"></div>
          </div>
          <div class="exam-mode-content">
            <div class="exam-mode-title">
              <span>Gunakan Jam Reguler Kelas</span>
            </div>
            <p class="exam-mode-desc">
              ${regularSchedule ? `${esc(['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][Number(regularSchedule.hari_ke)] || '')} · ${esc(String(regularSchedule.jam_mulai || '').slice(0, 5))}–${esc(String(regularSchedule.jam_selesai || '').slice(0, 5))}` : 'Tidak ada jadwal reguler'}
            </p>
            <span class="exam-mode-hint">${regularSchedule ? `Lab: ${esc(regularSchedule.nama_laboratorium || regularSchedule.kode_laboratorium || 'Sesuai reguler')}` : 'Belum disetel'}</span>
          </div>
        </button>
        <button type="button" class="exam-mode-card ${regularSchedule ? '' : 'is-active'}" id="api-exam-use-custom" role="radio" aria-checked="${regularSchedule ? 'false' : 'true'}">
          <div class="exam-mode-radio">
            <div class="exam-radio-dot"></div>
          </div>
          <div class="exam-mode-content">
            <div class="exam-mode-title">
              <span>Jadwal & Waktu Khusus</span>
            </div>
            <p class="exam-mode-desc">Tentukan hari, jam, atau ruangan lab lain secara fleksibel</p>
            <span class="exam-mode-hint">Pola perulangan sekali ujian</span>
          </div>
        </button>
      </div>
    ` : '';

    const totalMeetings = Number(c.jumlah_pertemuan_rencana) || 12;
    const defaultMidMeeting = Math.min(Math.max(1, Math.round(totalMeetings / 2)), totalMeetings);
    const linkedMeetingNumber = d.nomor_pertemuan || (c.sesi || []).find(s => Number(s.id_jadwal_kelas_kuliah) === Number(d.id))?.nomor_pertemuan || null;
    const recommendedMeeting = isExam && !schedule
      ? (effectiveExamType === 'MID' ? defaultMidMeeting : totalMeetings)
      : linkedMeetingNumber;

    const meetingOptionsHtml = Array.from({ length: totalMeetings }, (_, i) => {
      const num = i + 1;
      const isMidRec = isExam && effectiveExamType === 'MID' && num === defaultMidMeeting;
      const isUasRec = isExam && effectiveExamType === 'UAS' && num === totalMeetings;
      const recTag = isMidRec ? ' (Rekomendasi UTS/MID)' : (isUasRec ? ' (Rekomendasi UAS)' : '');
      const existingSession = (c.sesi || []).find(s => Number(s.nomor_pertemuan) === num);
      const sessionTag = existingSession ? ` · Terisi: ${esc(existingSession.materi || 'Sesi')}` : '';
      const isSelected = Number(recommendedMeeting) === num;
      return `<option value="${num}" ${isSelected ? 'selected' : ''}>Pertemuan ${num}${recTag}${sessionTag}</option>`;
    }).join('');

    const modal = dialog(`<form class="modal-schedule-form">
      <div class="modal-schedule-header">
        <div class="modal-header-left">
          <div class="modal-icon-badge ${badgeColorClass}">${badgeIcon}</div>
          <div class="modal-title-wrap">
            <h2>${esc(modalTitle)}</h2>
            <p>${modalSubtitle}</p>
          </div>
        </div>
      </div>

      <div class="modal-schedule-body">
        ${examPresetHtml}
        <div id="api-room-conflict-info" class="exam-conflict-alert" hidden></div>

        <div class="form-section-card">
          <div class="form-section-header">
            <span class="section-icon">${iconSvg('calendar')}</span>
            <span>Waktu & Lokasi Pelaksanaan</span>
          </div>

          <div class="form-field-group">
            <label>Laboratorium <span style="color:var(--rose)">*</span></label>
            <select name="id_laboratorium" required>
              <option value="">Pilih laboratorium…</option>
              ${refs.laboratorium.map(x => `<option value="${Number(x.id)}" ${Number(d.id_laboratorium) === Number(x.id) ? 'selected' : ''}>${esc(x.kode)} · ${esc(x.nama)}</option>`).join('')}
            </select>
          </div>

          <div class="form-field-group">
            <label>
              <span>Masukkan ke Pertemuan Praktikum</span>
              <span style="font-weight:normal;color:var(--text-muted);font-size:11px">Opsional</span>
            </label>
            <select name="nomor_pertemuan">
              <option value="">— Tidak dimasukkan ke pertemuan tertentu —</option>
              ${meetingOptionsHtml}
            </select>
            <p class="field-help-text">Jika dipilih, tanggal, jam, lab, dan materi ujian (${effectiveExamType || 'MID/UAS'}) otomatis disinkronkan ke sesi pertemuan tersebut.</p>
          </div>

          <div class="form-row-grid-2">
            <div class="form-field-group">
              <label>
                <span>Tanggal Pelaksanaan</span>
                ${isExam ? '<span style="color:var(--rose)">*</span>' : '<span style="font-weight:normal;color:var(--text-muted);font-size:11px">Khusus pola Sekali</span>'}
              </label>
              <input name="tanggal_sekali" type="date" ${(d.pola_perulangan === 'SEKALI' || isExam) ? 'required' : 'disabled'} value="${(d.pola_perulangan === 'SEKALI' || isExam) ? esc(d.tanggal_mulai || '') : ''}">
              <p class="field-help-text">Hari akan disesuaikan otomatis saat tanggal dipilih.</p>
            </div>

            <div class="form-field-group">
              <label>Hari <span style="color:var(--rose)">*</span></label>
              <select name="hari_ke" required>
                ${['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map((x, i) => `<option value="${i + 1}" ${Number(d.hari_ke) === (i + 1) ? 'selected' : ''}>${x}</option>`).join('')}
              </select>
            </div>
          </div>

          <div class="form-row-grid-2">
            <div class="form-field-group">
              <label>Jam Mulai <span style="color:var(--rose)">*</span></label>
              <input name="jam_mulai" type="time" value="${esc(String(d.jam_mulai || '08:00').slice(0, 5))}" required>
            </div>

            <div class="form-field-group">
              <label>Jam Selesai <span style="color:var(--rose)">*</span></label>
              <input name="jam_selesai" type="time" value="${esc(String(d.jam_selesai || '10:00').slice(0, 5))}" required>
            </div>
          </div>

          ${!isExam ? `
          <div class="form-row-grid-2">
            <div class="form-field-group">
              <label>Pola Perulangan <span style="color:var(--rose)">*</span></label>
              <select name="pola_perulangan" required>
                ${(refs.pola_perulangan || []).map(x => `<option value="${esc(x.kode)}" ${(d.pola_perulangan || 'MINGGUAN') === x.kode ? 'selected' : ''}>${esc(x.nama)}</option>`).join('')}
              </select>
            </div>

            <div class="form-field-group">
              <label>Status</label>
              <select name="status">
                <option value="AKTIF" ${d.status === 'NONAKTIF' ? '' : 'selected'}>Aktif</option>
                <option value="NONAKTIF" ${d.status === 'NONAKTIF' ? 'selected' : ''}>Nonaktif</option>
              </select>
            </div>
          </div>
          ` : `
          <input type="hidden" name="pola_perulangan" value="SEKALI">
          <input type="hidden" name="status" value="${d.status || 'AKTIF'}">
          `}
        </div>

        <div class="form-section-card">
          <div class="form-section-header">
            <span class="section-icon">${iconSvg('users')}</span>
            <span>${isExam ? 'Dosen Pengampu & Tim Pengawas Ujian' : 'Dosen Pengampu & Tim Asisten'}</span>
          </div>

          <div class="form-field-group">
            <label>Dosen Pengampu <span style="color:var(--rose)">*</span></label>
            <select name="id_user_dosen" required>
              <option value="">Pilih dosen…</option>
              ${refs.dosen.map(x => `<option value="${Number(x.id_user)}" ${Number(selected('DOSEN_PENGAMPU')) === Number(x.id_user) ? 'selected' : ''}>${esc(x.nama)}</option>`).join('')}
            </select>
          </div>

          <div class="form-row-grid-2">
            <div class="form-field-group">
              <label>Asisten 1 (Wajib) <span style="color:var(--rose)">*</span></label>
              <select name="id_user_asisten_utama" required>
                <option value="">Pilih asisten…</option>
                ${refs.asisten.map(x => `<option value="${Number(x.id_user)}" ${Number(selected('ASISTEN_UTAMA')) === Number(x.id_user) ? 'selected' : ''}>${esc(assistantOptionLabel(x))}</option>`).join('')}
              </select>
              <div class="assistant-availability is-neutral" data-assistant-availability="ASISTEN_UTAMA" aria-live="polite">Pilih waktu untuk memeriksa jadwal kuliah pribadi.</div>
            </div>

            <div class="form-field-group">
              <label>
                <span>Asisten 2</span>
                <span style="font-weight:normal;color:var(--text-muted);font-size:11px">Opsional</span>
              </label>
              <select name="id_user_asisten_pengajar_pendukung">
                <option value="">Tidak ada</option>
                ${refs.asisten.map(x => `<option value="${Number(x.id_user)}" ${Number(selected('ASISTEN_PENGAJAR_PENDUKUNG') || selected('ASISTEN_PENDAMPING')) === Number(x.id_user) ? 'selected' : ''}>${esc(assistantOptionLabel(x))}</option>`).join('')}
              </select>
              <div class="assistant-availability is-neutral" data-assistant-availability="ASISTEN_PENGAJAR_PENDUKUNG" aria-live="polite">Opsional.</div>
            </div>
          </div>

          <div class="form-field-group">
            <label>
              <span>Asisten Pendamping / Teknis</span>
              <span style="font-weight:normal;color:var(--text-muted);font-size:11px">Opsional</span>
            </label>
            <select name="id_user_pendamping_teknis">
              <option value="">Tidak ada</option>
              ${refs.asisten.map(x => `<option value="${Number(x.id_user)}" ${Number(selected('PENDAMPING_TEKNIS')) === Number(x.id_user) ? 'selected' : ''}>${esc(assistantOptionLabel(x))}</option>`).join('')}
            </select>
            <div class="assistant-availability is-neutral" data-assistant-availability="PENDAMPING_TEKNIS" aria-live="polite">Opsional.</div>
          </div>
        </div>
      </div>

      <div class="modal-schedule-footer">
        <div class="modal-footer-note">
          <span>${iconSvg('shield-check')}</span>
          <span>Bentrok kuliah pribadi adalah peringatan. Asisten tetap dapat dipilih.</span>
        </div>
        <div class="modal-footer-actions">
          <button type="button" class="btn-modal-cancel" data-close>Batal</button>
          <button type="submit" class="btn-modal-submit">
            <span>${isExam ? `Simpan Jadwal ${effectiveExamType}` : 'Simpan Jadwal'}</span>
            <span>→</span>
          </button>
        </div>
      </div>
    </form>`);

    modal.classList.add('modal-schedule');
    modal.addEventListener('close', () => modal.classList.remove('modal-schedule'), { once: true });

    const form = modal.querySelector('form');
    const patternControl = form.querySelector('[name="pola_perulangan"]');
    const oneTimeInput = form.querySelector('[name="tanggal_sekali"]');
    const periodStart = activePeriod?.start_date || c.periode?.tanggal_mulai || dateStart;
    const periodEnd = activePeriod?.end_date || c.periode?.tanggal_selesai || dateEnd;

    const applyScheduleDates = values => {
      if (values.pola_perulangan === 'SEKALI') {
        values.tanggal_mulai = oneTimeInput?.value || '';
        values.tanggal_selesai = oneTimeInput?.value || '';
      } else {
        values.tanggal_mulai = periodStart;
        values.tanggal_selesai = periodEnd;
      }
    };

    const syncScheduleDateInput = () => {
      const once = patternControl?.value === 'SEKALI';
      if (oneTimeInput) {
        oneTimeInput.disabled = !once;
        oneTimeInput.required = once;
        if (!once) oneTimeInput.value = '';
      }
    };

    patternControl?.addEventListener('change', () => {
      syncScheduleDateInput();
      refreshAssistantAvailability();
    });

    oneTimeInput?.addEventListener('change', () => {
      if (oneTimeInput.value) {
        const date = new Date(`${oneTimeInput.value}T00:00:00Z`);
        const weekday = date.getUTCDay() || 7;
        const day = form.querySelector('[name="hari_ke"]');
        if (!Number.isNaN(date.getTime()) && day) day.value = String(weekday);
      }
      refreshAssistantAvailability();
    });
    syncScheduleDateInput();

    const btnRegular = form.querySelector('#api-exam-use-regular');
    const btnCustom = form.querySelector('#api-exam-use-custom');

    if (btnRegular && regularSchedule) {
      btnRegular.onclick = () => {
        btnRegular.classList.add('is-active');
        btnRegular.setAttribute('aria-checked', 'true');
        btnCustom?.classList.remove('is-active');
        btnCustom?.setAttribute('aria-checked', 'false');

        if (form.querySelector('[name="pola_perulangan"]')) {
          form.querySelector('[name="pola_perulangan"]').value = 'SEKALI';
        }
        syncScheduleDateInput();

        if (regularSchedule.id_laboratorium) form.querySelector('[name="id_laboratorium"]').value = String(regularSchedule.id_laboratorium);
        if (regularSchedule.hari_ke) form.querySelector('[name="hari_ke"]').value = String(regularSchedule.hari_ke);
        if (regularSchedule.jam_mulai) form.querySelector('[name="jam_mulai"]').value = String(regularSchedule.jam_mulai).slice(0, 5);
        if (regularSchedule.jam_selesai) form.querySelector('[name="jam_selesai"]').value = String(regularSchedule.jam_selesai).slice(0, 5);

        const regularDosen = (regularSchedule.pengajar || []).find(p => p.peran === 'DOSEN_PENGAMPU')?.id_user;
        const regularAsisten1 = (regularSchedule.pengajar || []).find(p => p.peran === 'ASISTEN_UTAMA')?.id_user;
        const regularAsisten2 = (regularSchedule.pengajar || []).find(p => p.peran === 'ASISTEN_PENGAJAR_PENDUKUNG')?.id_user;
        const regularAsisten3 = (regularSchedule.pengajar || []).find(p => p.peran === 'PENDAMPING_TEKNIS')?.id_user;

        if (regularDosen && form.querySelector('[name="id_user_dosen"]')) form.querySelector('[name="id_user_dosen"]').value = String(regularDosen);
        if (regularAsisten1 && form.querySelector('[name="id_user_asisten_utama"]')) form.querySelector('[name="id_user_asisten_utama"]').value = String(regularAsisten1);
        if (regularAsisten2 && form.querySelector('[name="id_user_asisten_pengajar_pendukung"]')) form.querySelector('[name="id_user_asisten_pengajar_pendukung"]').value = String(regularAsisten2);
        if (regularAsisten3 && form.querySelector('[name="id_user_pendamping_teknis"]')) form.querySelector('[name="id_user_pendamping_teknis"]').value = String(regularAsisten3);

        toast(`Slot jam (${String(regularSchedule.jam_mulai).slice(0, 5)}–${String(regularSchedule.jam_selesai).slice(0, 5)}) dan lab disesuaikan dengan jadwal reguler. Silakan tentukan tanggal pelaksanaan.`);
        refreshAssistantAvailability();
      };
    }

    if (btnCustom) {
      btnCustom.onclick = () => {
        btnCustom.classList.add('is-active');
        btnCustom.setAttribute('aria-checked', 'true');
        btnRegular?.classList.remove('is-active');
        btnRegular?.setAttribute('aria-checked', 'false');

        if (form.querySelector('[name="pola_perulangan"]')) {
          form.querySelector('[name="pola_perulangan"]').value = 'SEKALI';
        }
        syncScheduleDateInput();
        toast('Mode jadwal khusus aktif. Silakan pilih tanggal, jam, dan lab ujian.');
      };
    }

    let availabilityRequest = 0, availabilityState = [], availabilityError = false;
    const refreshAssistantAvailability = async () => {
      const requestId = ++availabilityRequest, values = Object.fromEntries(new FormData(form)); applyScheduleDates(values);
      const paint = (role, kind, message) => { const slot = form.querySelector(`[data-assistant-availability="${role}"]`), select = slot?.previousElementSibling; if (slot) { slot.className = `assistant-availability is-${kind}`; slot.textContent = message } if (select) { select.classList.remove('assistant-select-warning', 'assistant-select-available', 'assistant-select-error'); if (kind === 'warning') select.classList.add('assistant-select-warning'); else if (kind === 'available') select.classList.add('assistant-select-available'); else if (kind === 'error') select.classList.add('assistant-select-error') } };
      if (!values.id_laboratorium || !values.hari_ke || !values.jam_mulai || !values.jam_selesai || !values.tanggal_mulai || !values.pola_perulangan || !values.id_user_asisten_utama) { availabilityState = []; availabilityError = false; paint('ASISTEN_UTAMA', 'neutral', 'Pilih waktu untuk memeriksa jadwal kuliah pribadi.'); paint('ASISTEN_PENGAJAR_PENDUKUNG', 'neutral', values.id_user_asisten_pengajar_pendukung ? 'Pilih waktu untuk memeriksa jadwal kuliah pribadi.' : 'Opsional.'); paint('PENDAMPING_TEKNIS', 'neutral', values.id_user_pendamping_teknis ? 'Pilih waktu untuk memeriksa jadwal kuliah pribadi.' : 'Opsional.'); return [] }
      values.versi = Number(c.kelompok.versi); for (const field of ['id_laboratorium', 'hari_ke', 'id_user_dosen', 'id_user_asisten_utama']) values[field] = Number(values[field]); for (const field of ['id_user_asisten_pengajar_pendukung', 'id_user_pendamping_teknis']) values[field] = values[field] ? Number(values[field]) : null; if (!values.tanggal_selesai) values.tanggal_selesai = null;
      availabilityError = false; paint('ASISTEN_UTAMA', 'checking', 'Memeriksa jadwal kuliah…'); paint('ASISTEN_PENGAJAR_PENDUKUNG', values.id_user_asisten_pengajar_pendukung ? 'checking' : 'neutral', values.id_user_asisten_pengajar_pendukung ? 'Memeriksa jadwal kuliah…' : 'Opsional.'); paint('PENDAMPING_TEKNIS', values.id_user_pendamping_teknis ? 'checking' : 'neutral', values.id_user_pendamping_teknis ? 'Memeriksa jadwal kuliah…' : 'Opsional.');
      try {
        const response = await api(`/classes/${Number(c.id_kelas_kuliah)}/schedule-assistant-availability`, values, 'POST'), items = response.rekomendasi_ketersediaan_asisten || [];
        const roomConflicts = response.informasi_ketersediaan_ruang || [];
        const roomInfoBox = form.querySelector('#api-room-conflict-info');
        if (roomInfoBox) {
          if (!roomConflicts.length) {
            roomInfoBox.hidden = true;
            roomInfoBox.innerHTML = '';
          } else {
            const hasForeignConflict = roomConflicts.some(x => !x.is_kelas_sendiri);
            roomInfoBox.hidden = false;
            if (!hasForeignConflict) {
              roomInfoBox.className = 'notice is-info';
              roomInfoBox.innerHTML = `<strong>Slot Jadwal Reguler Kelas</strong><p style="margin:4px 0 0">Waktu dan lab bertepatan dengan slot jadwal reguler kelas ini. Ujian dapat dilaksanakan pada jam tersebut tanpa bentrok.</p>`;
            } else {
              roomInfoBox.className = 'exam-conflict-alert';
              roomInfoBox.innerHTML = `
                <div class="conflict-alert-content">
                  <div class="conflict-alert-head">
                    <span>${iconSvg('shield-check')}</span>
                    <span>Keterangan Penggunaan Ruangan</span>
                  </div>
                  <p class="conflict-alert-desc">${roomConflicts.map(x => esc(x.pesan)).join('<br>')}</p>
                  <label class="conflict-confirm-check">
                    <input type="checkbox" name="izinkan_bentrok_ruang" value="1">
                    <span>Tetap gunakan ruangan ini dengan catatan ruang bersama (kegiatan telah dikoordinasikan)</span>
                  </label>
                </div>
              `;
            }
          }
        }
        if (requestId !== availabilityRequest) return availabilityState;
        availabilityState = items;
        for (const [role, field] of [['ASISTEN_UTAMA', 'id_user_asisten_utama'], ['ASISTEN_PENGAJAR_PENDUKUNG', 'id_user_asisten_pengajar_pendukung'], ['PENDAMPING_TEKNIS', 'id_user_pendamping_teknis']]) {
          const id = Number(values[field] || 0), item = items.find(candidate => Number(candidate.id_user) === id);
          if (!id) { paint(role, 'neutral', 'Opsional.'); continue }
          if (!item) { paint(role, 'neutral', 'Status ketersediaan tidak dikembalikan API.'); continue }
          const warnings = (item.peringatan || []).map(warning => warning.pesan).filter(Boolean), conflict = String(item.kode_ketersediaan || '').startsWith('BENTROK_'), unknown = item.kode_ketersediaan === 'JADWAL_BELUM_DIKIRIM';
          if (conflict) { paint(role, 'warning', `${warnings.join(' ') || 'Ada jadwal yang bertabrakan.'} Asisten tetap dapat dipilih.`) } else if (unknown) { paint(role, 'warning', 'Jadwal kuliah pribadi untuk periode ini belum tersedia; ketersediaan belum dapat dipastikan. Asisten tetap dapat dipilih.') } else paint(role, 'available', 'Tidak ada bentrok dengan jadwal yang tercatat.')
        }
        return items
      } catch (error) {
        if (requestId === availabilityRequest) { availabilityState = []; availabilityError = true; for (const [role, field] of [['ASISTEN_UTAMA', 'id_user_asisten_utama'], ['ASISTEN_PENGAJAR_PENDUKUNG', 'id_user_asisten_pengajar_pendukung'], ['PENDAMPING_TEKNIS', 'id_user_pendamping_teknis']]) paint(role, values[field] ? 'error' : 'neutral', values[field] ? `Pemeriksaan gagal: ${error.message}. Anda tetap dapat memilih secara manual.` : 'Opsional.') }
        return []
      }
    };
    let availabilityTimer; form.querySelectorAll('[name="hari_ke"],[name="jam_mulai"],[name="jam_selesai"],[name="tanggal_sekali"],[name="pola_perulangan"],[name="status"],[name="id_laboratorium"],[name="id_user_asisten_utama"],[name="id_user_asisten_pengajar_pendukung"],[name="id_user_pendamping_teknis"]').forEach(input => input.addEventListener('change', () => { clearTimeout(availabilityTimer); availabilityTimer = setTimeout(refreshAssistantAvailability, 180) })); refreshAssistantAvailability();
    modal.querySelector('form').onsubmit = async event => {
      event.preventDefault(); const button = event.target.querySelector('[type=submit]'); button.disabled = true;
      try {
        await refreshAssistantAvailability();
        const values = Object.fromEntries(new FormData(event.target));
        applyScheduleDates(values);
        for (const field of ['id_laboratorium', 'hari_ke', 'id_user_dosen', 'id_user_asisten_utama']) values[field] = Number(values[field]);
        for (const field of ['id_user_asisten_pengajar_pendukung', 'id_user_pendamping_teknis']) values[field] = values[field] ? Number(values[field]) : null;
        values.versi = Number(c.kelompok.versi);
        if (!values.tanggal_selesai) values.tanggal_selesai = null;
        if (values.nomor_pertemuan) {
          values.nomor_pertemuan = Number(values.nomor_pertemuan);
        } else {
          delete values.nomor_pertemuan;
        }
        if (isExam) {
          values.catatan = 'UJIAN_' + effectiveExamType;
          values.materi_sesi = `Ujian ${effectiveExamType === 'MID' ? 'Tengah' : 'Akhir'} Semester (${effectiveExamType})`;
        }
        if (event.target.querySelector('[name="izinkan_bentrok_ruang"]')?.checked) {
          values.izinkan_bentrok_ruang = true;
        }
        const saved = await api(schedule ? `/classes/${c.id_kelas_kuliah}/schedules/${schedule.id}` : `/classes/${c.id_kelas_kuliah}/schedules`, values, schedule ? 'PATCH' : 'POST'), savedWarnings = saved.rekomendasi_ketersediaan_asisten || [], hasSavedWarning = savedWarnings.length > 0;
        modal.close();
        toast(isExam ? `Jadwal ujian ${effectiveExamType}${values.nomor_pertemuan ? ` (Pertemuan ${values.nomor_pertemuan})` : ''} berhasil disimpan.` : (hasSavedWarning ? 'Jadwal tersimpan dengan peringatan ketersediaan Asisten.' : 'Jadwal tersimpan di API pusat.'));
        await apiClassDetailWithEntry(Number(c.id_kelas_kuliah));
      } catch (error) { toast(error.message); button.disabled = false }
    };
  } catch (error) { toast(error.message) }
}
async function apiSyncParticipants(c) {
  if (!confirm('Sinkronkan anggota kelompok Praktikum yang aktif? Peserta lama yang tidak lagi aktif tidak akan dihapus.')) return;
  try { const result = await api(`/classes/${Number(c.id_kelas_kuliah)}/participants/sync`, {}, 'POST'); toast(`${Number(result.jumlah_peserta_baru || 0)} peserta baru disinkronkan.`); await apiClassDetailWithEntry(Number(c.id_kelas_kuliah)) } catch (error) { toast(error.message) }
}
function apiLocalDateToday() { const now = new Date(); return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-') }
function apiTransferParticipant(c, student) {
  const candidates = classes.filter(x => Number(x.id_kelas_kuliah) !== Number(c.id_kelas_kuliah) && Number(x.periode.id) === Number(c.periode.id) && x.program_studi.kode === c.program_studi.kode && Number(x.mata_kuliah.id) === Number(c.mata_kuliah.id));
  if (!candidates.length) { toast('Tidak ada kelas tujuan dengan periode, program studi, dan mata kuliah yang sama.'); return }
  const modal = dialog(`<form><h2>Pindahkan mahasiswa</h2><p>${esc(student.nim)} · ${esc(student.nama)}</p><label>Kelas tujuan<select name="id_kelas_tujuan" required><option value="">Pilih kelas tujuan…</option>${candidates.map(x => `<option value="${Number(x.id_kelas_kuliah)}">${esc(x.kelompok.nama || x.kelompok.kode)} · Kode ${esc(x.kelompok.kode)} · ${esc(x.periode.tahun_akademik)} ${esc(x.periode.semester)}</option>`).join('')}</select></label><p class="help">Perpindahan berlaku mulai hari ini (${apiLocalDateToday()}).</p><label>Alasan<textarea name="alasan" maxlength="1000" required></textarea></label><p class="help">Riwayat presensi dan nilai lama tetap terhubung ke kelas asal. API akan menolak perpindahan yang melanggar status atau memiliki riwayat di tujuan.</p><div class="row"><button type="button" data-close>Batal</button><button class="primary" type="submit">Pindahkan</button></div></form>`);
  modal.querySelector('form').onsubmit = async event => { event.preventDefault(); const button = event.target.querySelector('[type=submit]'); button.disabled = true; try { const values = Object.fromEntries(new FormData(event.target)), target = candidates.find(x => Number(x.id_kelas_kuliah) === Number(values.id_kelas_tujuan)); if (!target) throw Error('Kelas tujuan tidak valid.'); await api(`/classes/${Number(c.id_kelas_kuliah)}/participants/transfer`, { versi_sumber: Number(c.kelompok.versi), versi_tujuan: Number(target.kelompok.versi), id_kelas_tujuan: Number(target.id_kelas_kuliah), id_mahasiswa: Number(student.id_mahasiswa), tanggal_efektif: apiLocalDateToday(), alasan: values.alasan }, 'POST'); modal.close(); toast('Mahasiswa berhasil dipindahkan; histori lama dipertahankan.'); classes = await api('/classes'); await apiClassDetailWithEntry(Number(c.id_kelas_kuliah)) } catch (error) { toast(error.message); button.disabled = false } };
}
function apiAttendanceDraftKey(classId, meeting, scheduleId) { return `iclabs:praktikum:attendance:v1:${Number(user?.id) || 0}:${Number(classId)}:${Number(meeting)}:${Number(scheduleId) || 0}` }
function apiReadAttendanceDraft(key) { try { const value = localStorage.getItem(key); return value ? JSON.parse(value) : null } catch { return null } }
function apiWriteAttendanceDraft(key, draft) { try { localStorage.setItem(key, JSON.stringify({ ...draft, updated_at: new Date().toISOString() })); return true } catch { return false } }
function apiClearAttendanceDraft(key) { try { localStorage.removeItem(key) } catch { } }
function apiGradeTone(value) { if (value === null || value === undefined || String(value).trim() === '') return 'empty'; const score = Number(value); if (!Number.isFinite(score) || score < 0 || score > 100) return 'invalid'; if (score === 0) return 'zero'; if (score < 50) return 'low'; if (score < 80) return 'mid'; return 'high' }
function apiPaintGradeInput(input) { const tone = apiGradeTone(input.value); input.dataset.gradeTone = tone; input.setAttribute('aria-invalid', String(tone === 'invalid')) }
function apiAssessmentSchemeForm(c, existing, selectedMeeting, selectedSchedule) {
  const defaults = [['Teori', 15], ['Studi Kasus', 35], ['Evaluasi Praktikum', 25], ['Asistensi', 25]], components = existing?.komponen?.length ? existing.komponen.map(x => [x.nama, x.bobot_persen]) : defaults;
  const modal = dialog(`<form><h2>Skema penilaian praktikum</h2><p>Bobot ini berlaku untuk seluruh lembar penilaian kelas. Skema baru akan langsung diterapkan ke lembar penilaian pertemuan.</p><div class="table-wrap"><table><thead><tr><th>KATEGORI</th><th>BOBOT (%)</th><th>AKSI</th></tr></thead><tbody id="api-assessment-components">${components.map(([name, weight]) => `<tr><td><input data-assessment-name maxlength="100" required value="${esc(name)}" aria-label="Nama kategori"></td><td><input data-assessment-weight type="number" min="0.01" max="100" step="0.01" required value="${Number(weight)}" aria-label="Bobot persen"></td><td><button type="button" class="small danger" data-remove-assessment>Hapus</button></td></tr>`).join('')}</tbody></table></div><div class="row between" style="margin-top:8px"><button type="button" class="small" id="api-add-assessment">+ Tambah kategori</button><p class="help" id="api-assessment-total" aria-live="polite" style="margin:0"></p></div><div class="row actions modal-actions" style="justify-content:flex-end"><button type="button" data-close>Batal</button><button class="primary" type="submit">Simpan versi skema</button></div></form>`);
  const body = modal.querySelector('#api-assessment-components'), total = modal.querySelector('#api-assessment-total');
  const updateTotal = () => { const sum = [...body.querySelectorAll('[data-assessment-weight]')].reduce((value, input) => value + (Number(input.value) || 0), 0); total.textContent = `Total bobot: ${sum.toFixed(2)}%${Math.abs(sum - 100) < 0.001 ? ' (Tepat 100%)' : ' · harus tepat 100%'}`; total.setAttribute('aria-invalid', String(Math.abs(sum - 100) >= 0.001)) };
  body.addEventListener('input', updateTotal); body.addEventListener('click', event => { if (event.target.matches('[data-remove-assessment]')) { event.target.closest('tr').remove(); updateTotal() } });
  modal.querySelector('#api-add-assessment').onclick = () => { if (body.querySelectorAll('tr').length >= 20) return; body.insertAdjacentHTML('beforeend', '<tr><td><input data-assessment-name maxlength="100" required aria-label="Nama kategori"></td><td><input data-assessment-weight type="number" min="0.01" max="100" step="0.01" required aria-label="Bobot persen"></td><td><button type="button" class="small danger" data-remove-assessment>Hapus</button></td></tr>'); updateTotal() };
  updateTotal();
  modal.querySelector('form').onsubmit = async event => { event.preventDefault(); const rows = [...body.querySelectorAll('tr')], weights = rows.map(row => Number(row.querySelector('[data-assessment-weight]').value)), names = rows.map(row => row.querySelector('[data-assessment-name]').value.trim()); if (rows.length < 1 || rows.length > 20 || weights.some(value => !Number.isFinite(value) || value <= 0) || Math.abs(weights.reduce((sum, value) => sum + value, 0) - 100) >= 0.001 || names.some(name => !name) || new Set(names.map(name => name.toLocaleLowerCase())).size !== names.length) { updateTotal(); toast('Periksa nama dan bobot. Total bobot harus tepat 100%.'); return } const button = event.currentTarget.querySelector('[type=submit]'); button.disabled = true; try { await api(`/classes/${Number(c.id_kelas_kuliah)}/assessment-scheme`, { versi: Number(c.kelompok.versi), komponen: rows.map((row, index) => ({ nama: names[index], bobot_persen: weights[index], urutan: index + 1 })) }, 'PUT'); modal.close(); toast('Skema penilaian baru berhasil disimpan dan diterapkan ke lembar pertemuan.'); window.apiDetailTab = 'grading'; await apiClassDetailWithEntry(Number(c.id_kelas_kuliah), selectedMeeting, selectedSchedule, 'grading') } catch (error) { toast(error.message); button.disabled = false } };
}
async function apiClassEditor(c, selectedMeeting, selectedSchedule) {
  const view = $('#api-view'), classId = Number(c.id_kelas_kuliah), meetings = Number(c.jumlah_pertemuan_rencana) || 1, schedules = c.jadwal || [], students = c.peserta || [], components = (c.komponen_nilai || []).filter(x => x.aktif), meeting = Number(selectedMeeting) || 1;
  const hasRoster = Object.prototype.hasOwnProperty.call(c, 'peserta');
  const isParticipant = c.peran_di_kelas === 'PRAKTIKAN' || Object.prototype.hasOwnProperty.call(c, 'nilai_saya') || !hasRoster;
  const isAssistant3 = c.peran_di_kelas === 'ASISTEN_3';
  const isLecturer = user.role === 'dosen' || c.peran_di_kelas === 'DOSEN';
  const canEdit = !isParticipant && !isAssistant3 && ['admin', 'asisten'].includes(user.role) && hasRoster;
  const weekday = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
  let assessmentScheme = null, meetingAssessment = null, assessmentError = '', lecturerAssessmentSheets = [], lecturerAssessmentError = '';
  const lecturerResultsView = isLecturer && page === 'Hasil Penilaian';
  if (canEdit) { try { [assessmentScheme, meetingAssessment] = await Promise.all([api(`/classes/${classId}/assessment-scheme`), api(`/classes/${classId}/meetings/${meeting}/assessment`)]) } catch (error) { assessmentError = error.message } }
  if (lecturerResultsView && c.hasil_diajukan) { try { const lecturerScheme = await api(`/classes/${classId}/assessment-scheme`); if (lecturerScheme) lecturerAssessmentSheets = await Promise.all(Array.from({ length: meetings }, (_, index) => api(`/classes/${classId}/meetings/${index + 1}/assessment`))) } catch (error) { lecturerAssessmentError = error.message } }
  const midSchedule = schedules.find(s => String(s.catatan_workflow || '').includes('MID'));
  const uasSchedule = schedules.find(s => String(s.catatan_workflow || '').includes('UAS'));
  const midMeetingNum = midSchedule?.nomor_pertemuan || (c.sesi || []).find(s => Number(s.id_jadwal_kelas_kuliah) === Number(midSchedule?.id))?.nomor_pertemuan || null;
  const uasMeetingNum = uasSchedule?.nomor_pertemuan || (c.sesi || []).find(s => Number(s.id_jadwal_kelas_kuliah) === Number(uasSchedule?.id))?.nomor_pertemuan || null;
  const autoScheduleId = (!selectedSchedule && Number(midMeetingNum) === meeting && midSchedule)
    ? Number(midSchedule.id)
    : ((!selectedSchedule && Number(uasMeetingNum) === meeting && uasSchedule) ? Number(uasSchedule.id) : (Number(selectedSchedule) || 0));
  const scheduleId = autoScheduleId, scheduleUnselected = schedules.length > 1 && !scheduleId, draftKey = apiAttendanceDraftKey(classId, meeting, scheduleId), draft = apiReadAttendanceDraft(draftKey) || {};
  const session = (c.sesi || []).find(x => Number(x.nomor_pertemuan) === meeting && (!scheduleId || Number(x.id_jadwal_kelas_kuliah || 0) === scheduleId)) || (c.sesi || []).find(x => Number(x.nomor_pertemuan) === meeting) || {};
  const attendanceFor = s => { const saved = (s.kehadiran || []).find(a => Number(a.nomor_pertemuan) === meeting && (!scheduleId || Number(a.id_jadwal_kelas_kuliah || 0) === scheduleId)) || (s.kehadiran || []).find(a => Number(a.nomor_pertemuan) === meeting) || {}; return draft.attendance?.[String(s.id_mahasiswa)] ? { ...saved, ...draft.attendance[String(s.id_mahasiswa)] } : saved };
  const gradeFor = (s, code) => (s.nilai || []).find(g => g.kode_komponen === code);
  const baseTabs = canEdit
    ? [['attendance', 'Presensi'], ['grading', 'Nilai pertemuan'], ['semester', 'Nilai akhir'], ['schedules', 'Jadwal & Ujian'], ['sessions', 'Riwayat sesi'], ['components', 'Komponen nilai'], ['roster', 'Rekap peserta']]
    : isAssistant3
      ? [['attendance', 'Presensi'], ['schedules', 'Jadwal & Ujian'], ['sessions', 'Riwayat sesi'], ['components', 'Komponen nilai'], ['roster', 'Daftar peserta']]
      : isParticipant
        ? [['schedules', 'Jadwal & Ujian'], ['sessions', 'Sesi praktikum'], ['components', 'Komponen nilai'], ['roster', 'Nilai Saya']]
        : isLecturer
          ? (lecturerResultsView ? [['grading', 'Hasil penilaian'], ['schedules', 'Jadwal & Verifikasi'], ['components', 'Komponen nilai'], ['roster', 'Rekap nilai']] : [['attendance', 'Rekap presensi'], ['schedules', 'Jadwal & Ujian'], ['sessions', 'Riwayat sesi'], ['components', 'Komponen nilai'], ['roster', 'Rekap nilai']])
          : hasRoster
            ? [['sessions', 'Sesi praktikum'], ['attendance', 'Presensi'], ['schedules', 'Jadwal & Ujian'], ['components', 'Komponen nilai'], ['roster', 'Rekap peserta']]
            : [['schedules', 'Jadwal & Ujian'], ['sessions', 'Sesi praktikum'], ['components', 'Komponen nilai'], ['roster', 'Nilai Saya']];
  const tabs = (hasRoster && !isParticipant) ? [...baseTabs, ['recap', 'Rekap Absensi']] : baseTabs;
  const tabIcons = {
    attendance: 'check-square',
    grading: 'file-text',
    semester: 'award',
    schedules: 'calendar',
    sessions: 'clock',
    components: 'layers',
    roster: 'users',
    recap: 'table'
  };
  const tabCounts = {
    schedules: schedules.length,
    sessions: (c.sesi || []).length,
    components: (c.komponen_nilai || []).length,
    roster: hasRoster ? students.length : null
  };
  let activeTab = tabs.some(([key]) => key === window.apiDetailTab)
    ? window.apiDetailTab
    : (canEdit ? 'attendance' : isAssistant3 ? 'attendance' : isParticipant ? 'roster' : isLecturer ? (lecturerResultsView ? 'grading' : 'attendance') : 'sessions');
  window.apiDetailTab = activeTab;
  const canDeleteDraftSchedule = user.role === 'admin' && Number(c.periode.id) === Number(activePeriod?.id) && c.status === 'DRAF';
  const countWorkflowEditable = schedules.every(schedule => ['DRAF', 'PERLU_REVISI'].includes(schedule.status_workflow || 'DRAF'));
  const canManageClassCounts = !isParticipant && !isAssistant3 && ['admin', 'asisten'].includes(user.role) && Number(c.periode.id) === Number(activePeriod?.id) && ['DRAF', 'AKTIF'].includes(c.status) && countWorkflowEditable;
  const scheduleRows = schedules.length ? schedules.map(s => {
    const isMid = String(s.catatan_workflow || '').includes('MID');
    const isUas = String(s.catatan_workflow || '').includes('UAS');
    const examBadge = isMid ? '<span class="badge yellow">UJIAN MID</span>' : (isUas ? '<span class="badge purple">UJIAN UAS</span>' : '');
    const staffChips = (s.pengajar || []).length
      ? (s.pengajar || []).map(p => `<div class="staff-chip"><span class="staff-role">${esc(p.nama_peran || p.peran)}</span><strong>${esc(p.nama || '—')}</strong></div>`).join('')
      : '<span class="muted">Belum ada penugasan pengajar</span>';
    return `<div class="schedule-card api-detail-row">
      <div class="schedule-card-top">
        <div class="row" style="gap:8px">
          <span class="schedule-time-pill">${iconSvg('clock')} ${esc(weekday[Number(s.hari_ke)] || 'Hari belum ditentukan')} · ${esc(String(s.jam_mulai || '').slice(0, 5))}–${esc(String(s.jam_selesai || '').slice(0, 5))}</span>
          <span class="badge">${esc(apiStatus(s.pola_perulangan))}</span>
          ${examBadge}
        </div>
        <div class="row" style="gap:8px">
          <span class="badge ${s.status === 'AKTIF' ? 'green' : 'gray'}">${esc(apiStatus(s.status))}</span>
        </div>
      </div>
      <div class="schedule-card-grid">
        <div class="schedule-meta-box">
          <span class="meta-label">${iconSvg('map-pin')} Ruangan & Rentang Tanggal</span>
          <strong>${esc(s.nama_laboratorium || s.nama_ruang || s.kode_ruang || 'Ruang belum ditentukan')}</strong>
          <small class="muted">${esc(s.tanggal_mulai || '—')} s/d ${esc(s.tanggal_selesai || '—')}</small>
        </div>
        <div class="schedule-meta-box">
          <span class="meta-label">${iconSvg('users')} Tim Pengajar & Asisten</span>
          <div class="schedule-staff-chips">${staffChips}</div>
          <div class="schedule-workflow" style="margin-top:8px">${apiWorkflowIndicator(s, user.role)}</div>
        </div>
      </div>
      ${canDeleteDraftSchedule ? `<div class="row" style="margin-top:10px"><button type="button" class="small danger" data-delete-draft-schedule="${Number(s.id)}">${iconSvg('trash')} <span>Hapus jadwal draft</span></button></div>` : ''}
    </div>`;
  }).join('') : '<div class="empty">Jadwal praktikum belum tersedia.</div>';
  const sessionRows = (c.sesi || []).length ? `<div class="table-wrap"><table><thead><tr><th class="num">Pertemuan</th><th>Tanggal</th><th>Topik / Materi</th><th>Status</th></tr></thead><tbody>${c.sesi.map(s => `<tr><td class="num">${Number(s.nomor_pertemuan)}</td><td>${esc(s.tanggal_sesi || '—')}</td><td>${esc([s.topik, s.materi].filter(Boolean).join(' · ') || '—')}</td><td>${esc(apiStatus(s.status))}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada sesi yang tercatat.</div>';
  const componentRows = (c.komponen_nilai || []).length ? `<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th class="num">Bobot</th><th>Status</th></tr></thead><tbody>${c.komponen_nilai.map(x => `<tr><td>${esc(x.kode)}</td><td>${esc(x.nama)}</td><td class="num">${Number(x.bobot_persen)}%</td><td>${x.aktif ? 'Aktif' : 'Nonaktif'}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Komponen nilai belum disiapkan.</div>';
  const attendanceRows = students.length ? `<div class="table-wrap"><table><thead><tr><th class="num">No</th><th>NIM</th><th>Nama</th><th>Status</th><th>Catatan</th></tr></thead><tbody>${students.map((s, index) => { const a = attendanceFor(s); return `<tr><td class="num">${index + 1}</td><td>${esc(s.nim)}</td><td>${esc(s.nama)}</td><td><select data-api-attendance="${Number(s.id_mahasiswa)}"><option value="">Belum diisi</option>${[['HADIR', 'Hadir'], ['IZIN', 'Izin'], ['SAKIT', 'Sakit'], ['ALPA', 'Alpa'], ['TERLAMBAT', 'Terlambat']].map(([v, l]) => `<option value="${v}" ${a.status === v ? 'selected' : ''}>${l}</option>`).join('')}</select></td><td><input data-api-attendance-note="${Number(s.id_mahasiswa)}" value="${esc(a.catatan || '')}" maxlength="1000" placeholder="Catatan opsional"></td></tr>` }).join('')}</tbody></table></div>` : '<div class="empty">Belum ada peserta aktif untuk dicatat.</div>';
  const attendanceTone = { HADIR: 'hadir', ALPA: 'alpa', IZIN: 'izin', SAKIT: 'sakit', TERLAMBAT: 'terlambat', BELUM_DIISI: 'belum' };
  const attendanceLabel = { HADIR: 'Hadir', ALPA: 'Alpa', IZIN: 'Izin', SAKIT: 'Sakit', TERLAMBAT: 'Terlambat', BELUM_DIISI: 'Belum diisi', BELUM_ADA: '—' };
  const attendanceMeetingColumns = Array.from({ length: meetings }, (_, index) => { const number = index + 1, related = (c.sesi || []).filter(item => Number(item.nomor_pertemuan) === number), dates = [...new Set(related.map(item => item.tanggal_sesi).filter(Boolean))]; return { number, dates } });
  const lecturerAttendanceRows = students.length ? `<div class="attendance-legend" aria-label="Keterangan status presensi">${[['HADIR', 'Hadir'], ['ALPA', 'Alpa'], ['IZIN', 'Izin'], ['SAKIT', 'Sakit'], ['TERLAMBAT', 'Terlambat'], ['BELUM_DIISI', 'Belum diisi']].map(([code, label]) => `<span class="attendance-pill attendance-${attendanceTone[code]}">${label}</span>`).join('')}</div><div class="table-wrap attendance-recap-wrap"><table class="attendance-recap"><thead><tr><th>NIM</th><th>Nama</th>${attendanceMeetingColumns.map(column => `<th><span>P${column.number}</span>${column.dates.length ? `<small>${column.dates.map(esc).join('<br>')}</small>` : ''}</th>`).join('')}</tr></thead><tbody>${students.map(student => `<tr><td>${esc(student.nim)}</td><td>${esc(student.nama)}</td>${attendanceMeetingColumns.map(column => { const records = (student.kehadiran || []).filter(item => Number(item.nomor_pertemuan) === column.number); if (!records.length) { const hasSession = column.dates.length > 0, code = hasSession ? 'BELUM_DIISI' : 'BELUM_ADA'; return `<td class="attendance-status-cell"><span class="attendance-pill attendance-${attendanceTone[code] || 'belum-ada'}">${attendanceLabel[code]}</span></td>` } return `<td class="attendance-status-cell">${records.map(record => { const code = String(record.status || 'BELUM_DIISI').toUpperCase(), tone = attendanceTone[code] || 'belum'; return `<span class="attendance-pill attendance-${tone}" title="${esc(record.catatan || attendanceLabel[code] || code)}">${esc(attendanceLabel[code] || apiStatus(code))}</span>` }).join('<br>')}</td>` }).join('')}</tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada mahasiswa aktif pada kelas ini.</div>';
  const assessmentSheet = meetingAssessment?.lembar || null, assessmentComponents = assessmentSheet?.skema?.komponen || assessmentScheme?.komponen || [];
  const gradeRows = assessmentError ? `<div class="error" role="alert">${esc(assessmentError)}</div>` : !assessmentScheme && !assessmentSheet ? '<div class="empty">Skema pertemuan belum disiapkan. Atur kategori dan total bobot sebelum membuat lembar nilai.</div>' : assessmentSheet ? `<div class="card-body"><p>Pertemuan ${meeting} · ${esc(assessmentSheet.tanggal_sesi || 'tanggal belum tersedia')} · Skema versi ${Number(assessmentSheet.skema.nomor_versi)} · ${assessmentSheet.peserta.filter(item => item.aktif && item.lengkap).length}/${assessmentSheet.peserta.filter(item => item.aktif).length} peserta aktif lengkap.</p></div><div class="table-wrap"><table><thead><tr><th>NIM</th><th>Nama</th>${assessmentComponents.map(item => `<th class="num">${esc(item.nama)} · ${Number(item.bobot_persen)}%</th>`).join('')}<th class="num">Nilai pertemuan</th></tr></thead><tbody>${assessmentSheet.peserta.map(student => `<tr><td>${esc(student.nim)}</td><td>${esc(student.nama || '—')}${student.aktif ? '' : ' <small>(nonaktif · hanya baca)</small>'}</td>${student.nilai.map(score => `<td class="num"><input class="api-grade-score" type="number" min="0" max="100" step="0.01" data-api-meeting-grade="${Number(student.id_mahasiswa)}" data-assessment-component="${Number(score.id_komponen_skema_penilaian_praktikum)}" data-active="${student.aktif ? '1' : '0'}" value="${score.nilai === null ? '' : esc(score.nilai)}" aria-label="${esc(student.nama || student.nim)} ${esc(score.nama_komponen)}" ${student.aktif ? '' : 'disabled'}></td>`).join('')}<td class="num">${student.nilai_pertemuan === null ? '—' : Number(student.nilai_pertemuan).toFixed(2)}</td></tr>`).join('')}</tbody></table></div>` : meetingAssessment?.siap_generate ? `<div class="warning">Presensi lengkap. Buat lembar nilai pertemuan ${meeting} memakai skema aktif versi ${Number(assessmentScheme.nomor_versi)}.</div>` : `<div class="warning">${esc(meetingAssessment?.alasan || 'Simpan presensi semua peserta sebelum membuat lembar nilai.')}</div>`;
  const legacyGradeRows = students.length && components.length ? `<div class="table-wrap"><table><thead><tr><th>NIM</th><th>Nama</th>${components.map(item => {
    let extra = '';
    if (item.kode === 'MID' && midSchedule) {
      extra = `<br><small style="font-weight:normal;color:var(--text-muted)">${esc(midSchedule.tanggal_mulai || '')} (${esc(midSchedule.nama_laboratorium || midSchedule.nama_ruang || 'Lab')})</small>`;
    } else if (item.kode === 'UAS' && uasSchedule) {
      extra = `<br><small style="font-weight:normal;color:var(--text-muted)">${esc(uasSchedule.tanggal_mulai || '')} (${esc(uasSchedule.nama_laboratorium || uasSchedule.nama_ruang || 'Lab')})</small>`;
    }
    return `<th class="num">${esc(item.nama)} · ${Number(item.bobot_persen)}%${extra}</th>`;
  }).join('')}</tr></thead><tbody>${students.map(student => `<tr><td>${esc(student.nim)}</td><td>${esc(student.nama)}</td>${components.map(item => { const grade = gradeFor(student, item.kode), score = grade?.nilai ?? ''; return `<td class="num"><input class="api-grade-score" type="number" min="0" max="100" step="0.01" data-api-final-grade="${Number(student.id_mahasiswa)}" data-component="${esc(item.kode)}" value="${esc(score)}" aria-label="${esc(student.nama)} ${esc(item.nama)}"></td>` }).join('')}</tr>`).join('')}</tbody></table></div>` : '<div class="empty">Komponen nilai akhir semester atau peserta belum tersedia.</div>';
  const gradeReadRows = students.length && components.length ? `<div class="table-wrap"><table><thead><tr><th>NIM</th><th>Nama</th>${components.map(x => `<th class="num">${esc(x.nama)} · ${Number(x.bobot_persen)}%</th>`).join('')}</tr></thead><tbody>${students.map(s => `<tr><td>${esc(s.nim)}</td><td>${esc(s.nama)}</td>${components.map(x => { const g = gradeFor(s, x.kode); return `<td class="num">${g && g.nilai !== null ? Number(g.nilai).toFixed(2) : '—'}</td>` }).join('')}</tr>`).join('')}</tbody></table></div>` : '<div class="empty">Nilai belum diajukan oleh Asisten.</div>';
  const meetingGradeReadRows = lecturerAssessmentError ? `<div class="error" role="alert">Rincian nilai pertemuan belum dapat dimuat: ${esc(lecturerAssessmentError)}</div>` : lecturerAssessmentSheets.map((assessment, index) => { const sheet = assessment.lembar; if (!sheet) return `<div class="card-body"><h3>Pertemuan ${index + 1}</h3><p class="empty">Lembar nilai pertemuan belum dibuat.</p></div>`; const assessmentParts = sheet.skema.komponen || []; return `<div class="card-body"><h3>Pertemuan ${Number(sheet.nomor_pertemuan)} · ${esc(sheet.tanggal_sesi || 'tanggal belum tersedia')}</h3><p>Skema versi ${Number(sheet.skema.nomor_versi)} · ${sheet.peserta.filter(student => student.lengkap).length}/${sheet.peserta.length} peserta lengkap.</p><div class="table-wrap"><table><thead><tr><th>NIM</th><th>Nama</th>${assessmentParts.map(component => `<th class="num">${esc(component.nama)} · ${Number(component.bobot_persen)}%</th>`).join('')}<th class="num">Nilai pertemuan</th></tr></thead><tbody>${sheet.peserta.map(student => `<tr><td>${esc(student.nim)}</td><td>${esc(student.nama || '—')}</td>${student.nilai.map(score => `<td class="num">${score.nilai === null ? '—' : Number(score.nilai).toFixed(2)}</td>`).join('')}<td class="num">${student.nilai_pertemuan === null ? 'Belum lengkap' : Number(student.nilai_pertemuan).toFixed(2)}</td></tr>`).join('')}</tbody></table></div></div>` }).join('') || '<div class="empty">Belum ada ringkasan nilai pertemuan.</div>';
  const fmt = value => value === null || value === undefined ? '—' : Number(value).toFixed(2);
  let roster = '';
  if (isAssistant3) {
    roster = `<div class="card-head"><h2>Daftar peserta · ${students.length}</h2><p>Asisten 3 (pendamping teknis) dapat melihat daftar mahasiswa aktif dan presensi.</p></div><div class="table-wrap"><table><thead><tr><th>NIM</th><th>Nama</th><th class="num">Total hadir</th></tr></thead><tbody>${students.map(s => `<tr><td>${esc(s.nim)}</td><td>${esc(s.nama)}</td><td class="num">${(s.kehadiran || []).filter(a => a.status === 'HADIR').length} dari ${(s.kehadiran || []).length} sesi tercatat</td></tr>`).join('') || '<tr><td colspan="3">Belum ada peserta aktif.</td></tr>'}</tbody></table></div>`;
  } else if (isParticipant) {
    const studentGradesTable = (c.nilai_saya || []).length ? `<div class="table-wrap"><table><thead><tr><th>Komponen</th><th class="num">Bobot</th><th class="num">Nilai</th></tr></thead><tbody>${c.nilai_saya.map(item => `<tr><td>${esc(item.nama_komponen || item.kode_komponen)}</td><td class="num">${item.bobot_persen != null ? Number(item.bobot_persen) + '%' : '—'}</td><td class="num"><strong>${fmt(item.nilai)}</strong></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada nilai yang dicatat untuk akun Anda.</div>';
    roster = `<div class="card-head"><h2>Nilai Saya</h2><p>Detail nilai dan bobot Anda pada kelas praktikum ini.</p></div><div class="card-body"><div class="stats"><div class="stat"><span class="label">Rata-rata Evaluasi</span><b>${fmt(c.ringkasan_nilai_saya?.rata_evaluasi)}</b></div><div class="stat"><span class="label">Rata-rata Asistensi</span><b>${fmt(c.ringkasan_nilai_saya?.rata_asistensi)}</b></div><div class="stat"><span class="label">Nilai Akhir</span><b>${fmt(c.ringkasan_nilai_saya?.nilai_akhir)}</b><small>${c.ringkasan_nilai_saya?.predikat ? `Predikat ${esc(c.ringkasan_nilai_saya.predikat)}` : (c.ringkasan_nilai_saya?.konfigurasi_nilai_tersedia ? 'Belum lengkap' : 'Konfigurasi nilai belum tersedia')}</small></div></div><p style="margin-top:12px">Kehadiran tercatat: ${(c.kehadiran_saya || []).length} sesi · Komponen nilai tercatat: ${(c.nilai_saya || []).length}</p></div>${studentGradesTable}`;
  } else if (Object.prototype.hasOwnProperty.call(c, 'peserta')) {
    roster = `<div class="card-head"><h2>Rekap peserta · ${students.length}</h2><p>Nilai akhir dihitung API pusat dengan rumus dan ambang Praktikum yang berlaku.</p></div><div class="table-wrap"><table><thead><tr><th>NIM</th><th>Nama</th><th class="num">Rata evaluasi</th><th class="num">Rata asistensi</th><th class="num">Nilai akhir</th><th>Predikat</th></tr></thead><tbody>${students.map(s => { const g = s.ringkasan_nilai || {}; return `<tr><td>${esc(s.nim)}</td><td>${esc(s.nama)}</td><td class="num">${g.rata_evaluasi == null ? '—' : Number(g.rata_evaluasi).toFixed(2)}</td><td class="num">${g.rata_asistensi == null ? '—' : Number(g.rata_asistensi).toFixed(2)}</td><td class="num">${g.nilai_akhir == null ? '—' : Number(g.nilai_akhir).toFixed(2)}</td><td>${esc(g.predikat || (!g.konfigurasi_nilai_tersedia ? 'Konfigurasi belum tersedia' : '—'))}</td></tr>` }).join('') || '<tr><td colspan="6">Belum ada peserta aktif.</td></tr>'}</tbody></table></div>`;
  } else {
    roster = `<div class="card-head"><h2>Ringkasan nilai Anda</h2></div><div class="card-body"><div class="stats"><div class="stat"><span class="label">Rata-rata Evaluasi</span><b>${fmt(c.ringkasan_nilai_saya?.rata_evaluasi)}</b></div><div class="stat"><span class="label">Rata-rata Asistensi</span><b>${fmt(c.ringkasan_nilai_saya?.rata_asistensi)}</b></div><div class="stat"><span class="label">Nilai Akhir</span><b>${fmt(c.ringkasan_nilai_saya?.nilai_akhir)}</b><small>${esc(c.ringkasan_nilai_saya?.predikat || (!c.ringkasan_nilai_saya?.konfigurasi_nilai_tersedia ? 'Konfigurasi nilai belum tersedia' : 'Belum lengkap'))}</small></div></div><p>Kehadiran tercatat: ${(c.kehadiran_saya || []).length} · Komponen nilai tercatat: ${(c.nilai_saya || []).length}</p><p>Detail nilai hanya memuat data akun Anda.</p></div>`;
  }
  const alpaCountOf = s => (s.kehadiran || []).filter(k => String(k.status || '').toUpperCase() === 'ALPA').length;
  const alpaToneOf = n => n >= 4 ? 'name-alpa-red' : n === 3 ? 'name-alpa-yellow' : '';
  const recapRows = students.map(s => { const list = s.kehadiran || [], cnt = code => list.filter(k => String(k.status || '').toUpperCase() === code).length, h = cnt('HADIR'), t = cnt('TERLAMBAT'), alpa = alpaCountOf(s); return { s, h, i: cnt('IZIN'), sk: cnt('SAKIT'), alpa, t, pct: meetings ? Math.round((h + t) / meetings * 100) : 0 } });
  const recapYellow = recapRows.filter(r => r.alpa === 3).length, recapRed = recapRows.filter(r => r.alpa >= 4).length;
  const recapSummaryTable = recapRows.length ? `<div class="table-wrap"><table class="attendance-recap-summary"><thead><tr><th class="num">No</th><th>NIM</th><th>Nama</th><th class="num">Hadir</th><th class="num">Izin</th><th class="num">Sakit</th><th class="num">Alpa</th><th class="num">Terlambat</th><th class="num">% Hadir</th></tr></thead><tbody>${recapRows.map((r, i) => `<tr><td class="num">${i + 1}</td><td>${esc(r.s.nim)}</td><td class="${alpaToneOf(r.alpa)}"><strong>${esc(r.s.nama)}</strong></td><td class="num">${r.h}</td><td class="num">${r.i}</td><td class="num">${r.sk}</td><td class="num ${alpaToneOf(r.alpa)}"><strong>${r.alpa}</strong></td><td class="num">${r.t}</td><td class="num">${r.pct}%</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada mahasiswa aktif pada kelas ini.</div>';
  const recapMeetingTable = recapRows.length ? `<div class="attendance-legend" aria-label="Keterangan status presensi">${[['HADIR', 'Hadir'], ['ALPA', 'Alpa'], ['IZIN', 'Izin'], ['SAKIT', 'Sakit'], ['TERLAMBAT', 'Terlambat'], ['BELUM_DIISI', 'Belum diisi']].map(([code, label]) => `<span class="attendance-pill attendance-${attendanceTone[code]}">${label}</span>`).join('')}</div><div class="table-wrap attendance-recap-wrap"><table class="attendance-recap"><thead><tr><th>NIM</th><th>Nama</th>${attendanceMeetingColumns.map(column => `<th><span>P${column.number}</span>${column.dates.length ? `<small>${column.dates.map(esc).join('<br>')}</small>` : ''}</th>`).join('')}<th class="num">Alpa</th></tr></thead><tbody>${recapRows.map(r => `<tr><td>${esc(r.s.nim)}</td><td class="${alpaToneOf(r.alpa)}"><strong>${esc(r.s.nama)}</strong></td>${attendanceMeetingColumns.map(column => { const records = (r.s.kehadiran || []).filter(item => Number(item.nomor_pertemuan) === column.number); if (!records.length) { const code = column.dates.length ? 'BELUM_DIISI' : 'BELUM_ADA'; return `<td class="attendance-status-cell"><span class="attendance-pill attendance-${attendanceTone[code] || 'belum-ada'}">${attendanceLabel[code]}</span></td>` } return `<td class="attendance-status-cell">${records.map(record => { const code = String(record.status || 'BELUM_DIISI').toUpperCase(); return `<span class="attendance-pill attendance-${attendanceTone[code] || 'belum'}" title="${esc(record.catatan || attendanceLabel[code] || code)}">${esc(attendanceLabel[code] || apiStatus(code))}</span>` }).join('<br>')}</td>` }).join('')}<td class="num ${alpaToneOf(r.alpa)}"><strong>${r.alpa}</strong></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Belum ada mahasiswa aktif pada kelas ini.</div>';
  const recapHtml = `<div class="card-head row between"><div><h2>Rekap Absensi</h2><p>Nama mahasiswa ditandai kuning pada 3 alpa dan merah pada 4 alpa atau lebih.</p></div><div class="row" style="gap:8px"><button type="button" class="small primary" data-recap-view="summary">${iconSvg('table')} <span>Ringkasan</span></button><button type="button" class="small" data-recap-view="meeting">${iconSvg('calendar')} <span>Per pertemuan</span></button></div></div><div class="card-body row" style="gap:10px"><span class="badge yellow">3 alpa: ${recapYellow} mahasiswa</span><span class="badge red">4+ alpa: ${recapRed} mahasiswa</span></div><div data-recap-pane="summary">${recapSummaryTable}</div><div data-recap-pane="meeting" hidden>${recapMeetingTable}</div>`;
  const syncClassBtn = ['admin', 'asisten'].includes(user.role) && !isParticipant && !isAssistant3
    ? `<button class="small primary btn-sync-action" id="api-sync-class" type="button" title="Ambil presensi dan nilai terbaru kelompok ini dari produksi">${iconSvg('refresh')} <span>Sinkronkan Frekuensi Ini</span></button>`
    : '';
  const primarySched = schedules[0] || null;
  const primaryTimeStr = primarySched
    ? `${esc(weekday[Number(primarySched.hari_ke)] || '—')}, ${esc(String(primarySched.jam_mulai || '').slice(0, 5))}–${esc(String(primarySched.jam_selesai || '').slice(0, 5))}`
    : 'Belum dijadwalkan';
  const primaryLabStr = primarySched
    ? esc(primarySched.nama_laboratorium || primarySched.nama_ruang || primarySched.kode_ruang || 'Ruang belum ditentukan')
    : `${schedules.length} jadwal tercatat`;
  view.innerHTML = `<div class="freq-header-card intro">
      <div class="freq-topbar">
        <div class="row" style="gap:8px">
          <button class="small btn-back" id="api-back" type="button">${iconSvg('arrow-left')} <span>Kembali ke kelas</span></button>
          ${user.role === 'admin' && Number(c.periode.id) === Number(activePeriod?.id) && c.status === 'DRAF' ? `<button class="small danger" id="api-delete-draft" type="button">${iconSvg('trash')} <span>Hapus kelas draft</span></button>` : ''}
        </div>
        <div class="row freq-actions" id="api-freq-actions" style="gap:8px">
          ${syncClassBtn}
        </div>
      </div>
      <div class="freq-hero-body">
        <div class="freq-title-row">
          <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
              <span class="badge blue">${esc(c.mata_kuliah.kode)}</span>
              <span class="badge ${c.status === 'AKTIF' ? 'green' : 'yellow'}">${esc(apiStatus(c.status))}</span>
              ${apiRoleBadge(c.peran_di_kelas)}
            </div>
            <h1>${esc(c.mata_kuliah.nama)}</h1>
          </div>
        </div>
        <div class="freq-meta-strip">
          <div class="freq-meta-item">
            <div>
              <span class="meta-label">${iconSvg('layers')} Frekuensi / Kelompok</span>
              <strong>${esc(c.kelompok.nama || c.kelompok.kode)}</strong>
              <small>Kode: ${esc(c.kelompok.kode)}</small>
            </div>
          </div>
          <div class="freq-meta-item">
            <div>
              <span class="meta-label">${iconSvg('calendar')} Program Studi & Periode</span>
              <strong>${esc(c.program_studi.nama)}</strong>
              <small>${esc(c.periode.tahun_akademik)} · ${esc(c.periode.semester)}</small>
            </div>
          </div>
          <div class="freq-meta-item">
            <div>
              <span class="meta-label">${iconSvg('clock')} Jadwal Reguler & Lab</span>
              <strong>${primaryTimeStr}</strong>
              <small>${primaryLabStr}</small>
            </div>
          </div>
          <div class="freq-meta-item">
            <div>
              <span class="meta-label">${iconSvg('users')} Target & Peserta</span>
              <strong>${meetings} Pertemuan</strong>
              <small>${hasRoster ? `${students.length} Mahasiswa terdaftar` : `${(c.sesi || []).length} Sesi tercatat`}</small>
            </div>
          </div>
        </div>
        <div class="freq-quick-bar">
          <div class="row" style="gap:10px;flex-wrap:wrap">
            ${primarySched ? `<div class="schedule-workflow">${apiWorkflowIndicator(primarySched, user.role)}</div>` : '<span class="badge gray">Belum ada jadwal</span>'}
            <span class="badge ${midSchedule ? 'yellow' : 'gray'}">MID: ${midSchedule ? `${esc(midSchedule.tanggal_mulai || 'Terjadwal')}` : 'Belum diatur'}</span>
            <span class="badge ${uasSchedule ? 'purple' : 'gray'}">UAS: ${uasSchedule ? `${esc(uasSchedule.tanggal_mulai || 'Terjadwal')}` : 'Belum diatur'}</span>
          </div>
          <button type="button" class="small btn-subtle-teal" id="api-quick-open-schedules">${iconSvg('calendar')} <span>Kelola Jadwal & Ujian (${schedules.length})</span></button>
        </div>
        ${c.catatan ? `<div class="warning" style="margin-top:12px">${esc(c.catatan)}</div>` : ''}
      </div>
    </div>
    <div class="api-detail-tabs" role="tablist" aria-label="Bagian kelas praktikum">${tabs.map(([key, label]) => `<button type="button" role="tab" aria-selected="${key === activeTab}" data-api-detail-tab="${key}" class="${key === activeTab ? 'active' : ''}">${iconSvg(tabIcons[key] || 'layers')} <span>${label}</span>${tabCounts[key] != null ? `<span class="tab-count">${tabCounts[key]}</span>` : ''}</button>`).join('')}</div>
    <div class="api-detail-panel" data-api-panel="schedules" ${activeTab === 'schedules' ? '' : 'hidden'}>
      <section class="card"><div class="card-head row between"><div><h2>Jadwal dan penugasan</h2><p>Daftar jadwal pelaksanaan praktikum, tim pengajar/asisten, status verifikasi nilai, dan unduhan laporan.</p></div></div><div class="schedule-cards-list">${scheduleRows}</div></section>
      <section class="card exam-schedule-section" style="margin-top:16px">
        <div class="card-head row between">
          <div>
            <h2>Jadwal Ujian (MID & UAS)</h2>
            <p>Asisten dapat menentukan atau memperbarui jadwal pelaksanaan ujian MID dan UAS frekuensi ini.</p>
          </div>
        </div>
        <div class="exam-schedule-grid">
          <div class="exam-card">
            <div class="exam-card-header row between">
              <span><strong>MID (Ujian Tengah Semester)</strong></span>
              ${midSchedule ? '<span class="badge yellow">Terjadwal</span>' : '<span class="badge">Belum diatur</span>'}
            </div>
            <div class="exam-card-body">
              ${midSchedule ? `
                ${midMeetingNum ? `<p class="exam-meeting" style="margin-bottom:6px;font-weight:600;color:var(--primary);font-size:13px">Terhubung ke Pertemuan ${midMeetingNum}</p>` : ''}
                <p class="exam-time">${esc(midSchedule.tanggal_mulai || '—')} (${esc(weekday[Number(midSchedule.hari_ke)] || '')})</p>
                <p class="exam-lab">${esc(midSchedule.nama_laboratorium || midSchedule.nama_ruang || 'Lab')} · ${esc(String(midSchedule.jam_mulai || '').slice(0, 5))}–${esc(String(midSchedule.jam_selesai || '').slice(0, 5))}</p>
                <small class="muted">${midSchedule.tanggal_mulai === midSchedule.tanggal_selesai ? 'Jadwal Khusus (Pola Sekali)' : esc(apiStatus(midSchedule.pola_perulangan))}</small>
              ` : `
                <p class="muted">Jadwal MID belum ditentukan. Asisten dapat menggunakan jam reguler atau menentukan tanggal khusus.</p>
              `}
            </div>
            ${canEdit || user.role === 'admin' ? `
              <div class="exam-card-footer">
                <button type="button" class="small ${midSchedule ? '' : 'primary'}" id="api-btn-schedule-mid">
                  ${iconSvg(midSchedule ? 'edit' : 'calendar')} <span>${midSchedule ? 'Edit Jadwal MID' : 'Atur Jadwal MID'}</span>
                </button>
              </div>
            ` : ''}
          </div>

          <div class="exam-card">
            <div class="exam-card-header row between">
              <span><strong>UAS (Ujian Akhir Semester)</strong></span>
              ${uasSchedule ? '<span class="badge purple">Terjadwal</span>' : '<span class="badge">Belum diatur</span>'}
            </div>
            <div class="exam-card-body">
              ${uasSchedule ? `
                ${uasMeetingNum ? `<p class="exam-meeting" style="margin-bottom:6px;font-weight:600;color:var(--primary);font-size:13px">Terhubung ke Pertemuan ${uasMeetingNum}</p>` : ''}
                <p class="exam-time">${esc(uasSchedule.tanggal_mulai || '—')} (${esc(weekday[Number(uasSchedule.hari_ke)] || '')})</p>
                <p class="exam-lab">${esc(uasSchedule.nama_laboratorium || uasSchedule.nama_ruang || 'Lab')} · ${esc(String(uasSchedule.jam_mulai || '').slice(0, 5))}–${esc(String(uasSchedule.jam_selesai || '').slice(0, 5))}</p>
                <small class="muted">${uasSchedule.tanggal_mulai === uasSchedule.tanggal_selesai ? 'Jadwal Khusus (Pola Sekali)' : esc(apiStatus(uasSchedule.pola_perulangan))}</small>
              ` : `
                <p class="muted">Jadwal UAS belum ditentukan. Asisten dapat menggunakan jam reguler atau menentukan tanggal khusus.</p>
              `}
            </div>
            ${canEdit || user.role === 'admin' ? `
              <div class="exam-card-footer">
                <button type="button" class="small ${uasSchedule ? '' : 'primary'}" id="api-btn-schedule-uas">
                  ${iconSvg(uasSchedule ? 'edit' : 'calendar')} <span>${uasSchedule ? 'Edit Jadwal UAS' : 'Atur Jadwal UAS'}</span>
                </button>
              </div>
            ` : ''}
          </div>
        </div>
      </section>
    </div>
    ${canEdit ? `<section class="card api-detail-panel" data-api-panel="attendance" ${activeTab === 'attendance' ? '' : 'hidden'}><div class="card-head"><h2>Presensi mahasiswa</h2><p>Draft otomatis disimpan di browser ini dan baru dihapus setelah berhasil dikirim ke API.</p></div>${scheduleUnselected ? '<div class="warning">Pilih jadwal sesi terlebih dahulu sebelum mencatat presensi.</div>' : ''}<div class="card-body api-edit-controls"><label>Pertemuan<select id="api-meeting">${Array.from({ length: meetings }, (_, i) => { const num = i + 1, tag = (Number(midMeetingNum) === num) ? ' (MID)' : ((Number(uasMeetingNum) === num) ? ' (UAS)' : ''); return `<option value="${num}" ${num === meeting ? 'selected' : ''}>Pertemuan ${num}${tag}</option>` }).join('')}</select></label>${schedules.length > 1 ? `<label>Jadwal sesi<select id="api-schedule"><option value="">Pilih jadwal…</option>${schedules.map(s => `<option value="${Number(s.id)}" ${Number(s.id) === scheduleId ? 'selected' : ''}>${esc(weekday[Number(s.hari_ke)] || 'Hari')} · ${esc(String(s.jam_mulai || '').slice(0, 5))} · ${esc(s.nama_laboratorium || s.nama_ruang || 'Laboratorium')}</option>`).join('')}</select></label>` : ''}<label>Tanggal sesi<input id="api-session-date" type="date" value="${esc(draft.tanggal_sesi || session.tanggal_sesi || '')}" ${scheduleUnselected ? 'disabled' : ''}></label><label class="api-material">Materi sesi<input id="api-session-material" maxlength="1000" value="${esc(draft.materi ?? session.materi ?? '')}" placeholder="Materi praktikum" ${scheduleUnselected ? 'disabled' : ''}></label></div>${scheduleUnselected ? '<div class="table-wrap"><table><thead><tr><th>NO</th><th>NIM</th><th>NAMA</th><th>STATUS</th><th>CATATAN</th></tr></thead><tbody>' + students.map((s, index) => `<tr><td>${index + 1}</td><td>${esc(s.nim)}</td><td>${esc(s.nama)}</td><td><select disabled><option>Belum diisi</option></select></td><td><input disabled></td></tr>`).join('') + '</tbody></table></div>' : attendanceRows}<div class="card-body row between"><small id="api-draft-status">${Object.keys(draft.attendance || {}).length || draft.materi || draft.tanggal_sesi ? 'Draft tersimpan di perangkat ini.' : 'Belum ada draft lokal.'}</small><div class="row"><button type="button" class="small" id="api-clear-draft" ${Object.keys(draft.attendance || {}).length || draft.materi || draft.tanggal_sesi ? '' : 'hidden'}>${iconSvg('trash')} <span>Hapus draft</span></button><button class="primary" id="api-save-attendance" ${scheduleUnselected ? 'disabled' : ''}>${iconSvg('save')} <span>Simpan presensi</span></button></div></div><p class="help">Draft hanya ada di browser/perangkat ini, bukan di server atau perangkat lain. Hindari perangkat bersama; setelah tersimpan ke API, draft lokal otomatis dihapus.</p></section>` : ''}
    ${canEdit ? `<section class="card api-detail-panel" data-api-panel="grading" ${activeTab === 'grading' ? '' : 'hidden'}><div class="card-head row between"><div><h2>Penilaian pertemuan ${meeting}</h2><p>Nilai disimpan per kategori dan dihitung memakai bobot pada versi skema lembar ini.</p></div><div class="row"><label class="inline-field">Pertemuan<select id="api-grade-meeting">${Array.from({ length: meetings }, (_, index) => { const num = index + 1, tag = (Number(midMeetingNum) === num) ? ' (MID)' : ((Number(uasMeetingNum) === num) ? ' (UAS)' : ''); return `<option value="${num}" ${num === meeting ? 'selected' : ''}>${num}${tag}</option>` }).join('')}</select></label><button type="button" class="small" id="api-assessment-scheme">${iconSvg('sliders')} <span>Atur skema</span></button>${!assessmentSheet && assessmentScheme && meetingAssessment?.siap_generate ? `<button type="button" class="primary small" id="api-generate-assessment">${iconSvg('plus')} <span>Buat lembar nilai</span></button>` : ''}</div></div>${gradeRows}${assessmentSheet ? `<div class="card-body row between"><small>Skema versi ${Number(assessmentSheet.skema.nomor_versi)} · versi lembar ${Number(assessmentSheet.versi_lembar)} · nilai 0 tetap dihitung.</small><button class="primary" id="api-save-meeting-grades">${iconSvg('save')} <span>Simpan nilai pertemuan</span></button></div>` : ''}</section>` : ''}
    ${canEdit ? `<section class="card api-detail-panel" data-api-panel="semester" ${activeTab === 'semester' ? '' : 'hidden'}><div class="card-head"><h2>Nilai akhir semester</h2><p>Komponen ini tetap mengikuti konfigurasi nilai akhir kelas yang sudah ada.</p></div>${legacyGradeRows}<div class="card-body row between"><small>Nilai akhir disimpan terpisah dari nilai tiap pertemuan.</small><button class="primary" id="api-save-final-grades">${iconSvg('save')} <span>Simpan nilai akhir</span></button></div></section>` : ''}
    <section class="card api-detail-panel" data-api-panel="sessions" ${activeTab === 'sessions' ? '' : 'hidden'}><div class="card-head"><h2>Sesi praktikum tercatat</h2></div>${sessionRows}</section>
    ${isLecturer && lecturerResultsView ? `<section class="card api-detail-panel" data-api-panel="grading" ${activeTab === 'grading' ? '' : 'hidden'}><div class="card-head"><h2>Hasil penilaian</h2><p>Nilai yang diajukan Asisten. Gunakan tindakan pada jadwal untuk meminta revisi, memverifikasi, atau mengunci hasil.</p></div>${c.hasil_diajukan ? `${gradeReadRows}<h3 class="card-body">Nilai kategori per pertemuan</h3>${meetingGradeReadRows}` : '<div class="warning">Hasil penilaian belum diajukan kepada Dosen.</div>'}<div class="card-body"><p class="help">Mode baca saja. Perubahan nilai hanya dapat dilakukan oleh Asisten yang ditugaskan.</p></div></section>` : ''}
    ${!canEdit && hasRoster && (isLecturer || isAssistant3) ? `<section class="card api-detail-panel" data-api-panel="attendance" ${activeTab === 'attendance' ? '' : 'hidden'}><div class="card-head"><h2>Rekap presensi mahasiswa</h2></div>${lecturerAttendanceRows}</section>` : ''}
    ${tabs.some(([k]) => k === 'recap') ? `<section class="card api-detail-panel" data-api-panel="recap" ${activeTab === 'recap' ? '' : 'hidden'}>${recapHtml}</section>` : ''}
    <section class="card api-detail-panel" data-api-panel="components" ${activeTab === 'components' ? '' : 'hidden'}><div class="card-head"><h2>Komponen nilai akhir semester</h2></div>${componentRows}</section>
    <section class="card api-detail-panel" data-api-panel="roster" ${activeTab === 'roster' ? '' : 'hidden'}>${roster}</section>
    `;
  $('#api-back').onclick = () => { if (window.apiDraftStorageFailed && !confirm('Draft presensi gagal disimpan pada perangkat ini. Tetap kembali?')) return; window.apiDraftStorageFailed = false; apiCurrent = null; apiClassListPage() };
  if ($('#api-delete-draft')) $('#api-delete-draft').onclick = () => apiDeleteDraftClass(c);
  if ($('#api-quick-open-schedules')) {
    $('#api-quick-open-schedules').onclick = () => {
      const schedTab = document.querySelector('[data-api-detail-tab="schedules"]');
      if (schedTab) {
        schedTab.click();
        schedTab.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    };
  }
  if ($('#api-sync-class')) {
    $('#api-sync-class').onclick = async () => {
      const freqCode = c.kelompok?.kode || c.kelompok?.nama;
      if (!confirm(`Mulai sinkronisasi data presensi dan nilai untuk kelompok "${freqCode}" dari database produksi legacy?`)) return;
      await apiSyncProduction($('#api-sync-class'), { id_kelas_kuliah: classId, kode_kelompok: freqCode }, async () => {
        await apiClassDetailWithEntry(classId, meeting, scheduleId, window.apiDetailTab);
      });
    };
  }
  if ($('#api-assessment-scheme')) $('#api-assessment-scheme').onclick = () => apiAssessmentSchemeForm(c, assessmentScheme || assessmentSheet?.skema, meeting, scheduleId);
  if ($('#api-grade-meeting')) $('#api-grade-meeting').onchange = () => { window.apiDetailTab = 'grading'; apiClassDetailWithEntry(classId, Number($('#api-grade-meeting').value), scheduleId, 'grading') };
  if ($('#api-generate-assessment')) $('#api-generate-assessment').onclick = async event => { const button = event.currentTarget; button.disabled = true; try { const payload = { versi: Number(c.kelompok.versi) }; if (scheduleId) payload.id_jadwal_kelas_kuliah = scheduleId; await api(`/classes/${classId}/meetings/${meeting}/assessment/generate`, payload, 'POST'); toast(`Lembar nilai pertemuan ${meeting} dibuat dengan skema aktif.`); window.apiDetailTab = 'grading'; await apiClassDetailWithEntry(classId, meeting, scheduleId, 'grading') } catch (error) { toast(error.message); button.disabled = false } };
  if ($('#api-save-meeting-grades')) $('#api-save-meeting-grades').onclick = async event => { const button = event.currentTarget; button.disabled = true; try { const inputs = [...document.querySelectorAll('[data-api-meeting-grade]')].filter(input => input.dataset.active !== '0'), invalid = inputs.find(input => !input.checkValidity()); if (invalid) { invalid.reportValidity(); invalid.focus(); throw Error('Nilai harus berada pada rentang 0 sampai 100.') } const payload = { versi: Number(c.kelompok.versi), versi_lembar: Number(assessmentSheet.versi_lembar), nilai: inputs.map(input => ({ id_mahasiswa: Number(input.dataset.apiMeetingGrade), id_komponen_skema_penilaian_praktikum: Number(input.dataset.assessmentComponent), nilai: input.value === '' ? null : Number(input.value) })) }; if (!payload.nilai.length) throw Error('Lembar nilai tidak memiliki komponen untuk disimpan.'); await api(`/classes/${classId}/meetings/${meeting}/grades`, payload, 'PUT'); toast('Nilai kategori dan nilai total pertemuan berhasil disimpan.'); window.apiDetailTab = 'grading'; await apiClassDetailWithEntry(classId, meeting, scheduleId, 'grading') } catch (error) { toast(error.message); button.disabled = false } };
  if ($('#api-save-final-grades')) $('#api-save-final-grades').onclick = async event => { const button = event.currentTarget; button.disabled = true; try { const inputs = [...document.querySelectorAll('[data-api-final-grade]')], invalid = inputs.find(input => !input.checkValidity()); if (invalid) { invalid.reportValidity(); invalid.focus(); throw Error('Nilai harus berada pada rentang 0 sampai 100.') } const nilai = inputs.map(input => ({ id_mahasiswa: Number(input.dataset.apiFinalGrade), kode_komponen: input.dataset.component, nilai: input.value === '' ? null : Number(input.value) })); if (!nilai.length) throw Error('Komponen nilai akhir belum tersedia.'); await api(`/classes/${classId}/changes`, { versi: Number(c.kelompok.versi), nilai }, 'PUT'); toast('Nilai akhir semester berhasil disimpan terpisah dari nilai pertemuan.'); window.apiDetailTab = 'semester'; await apiClassDetailWithEntry(classId, meeting, scheduleId, 'semester') } catch (error) { toast(error.message); button.disabled = false } };
  document.querySelectorAll('[data-recap-view]').forEach(button => button.onclick = () => { document.querySelectorAll('[data-recap-view]').forEach(b => b.classList.toggle('primary', b === button)); document.querySelectorAll('[data-recap-pane]').forEach(pane => pane.hidden = pane.dataset.recapPane !== button.dataset.recapView) });
  document.querySelectorAll('[data-delete-draft-schedule]').forEach(button => button.onclick = () => { const schedule = schedules.find(item => Number(item.id) === Number(button.dataset.deleteDraftSchedule)); if (schedule) apiDeleteDraftSchedule(c, schedule) });
  document.querySelectorAll('[data-api-detail-tab]').forEach(button => button.onclick = () => { window.apiDetailTab = button.dataset.apiDetailTab; document.querySelectorAll('[data-api-detail-tab]').forEach(tab => { const selected = tab === button; tab.classList.toggle('active', selected); tab.setAttribute('aria-selected', String(selected)) }); document.querySelectorAll('[data-api-panel]').forEach(panel => panel.hidden = panel.dataset.apiPanel !== button.dataset.apiDetailTab) });
  if (!canEdit && $('#api-meeting')) $('#api-meeting').onchange = () => apiClassEditor(c, Number($('#api-meeting').value), Number($('#api-schedule')?.value) || scheduleId);
  if (!canEdit && $('#api-schedule')) $('#api-schedule').onchange = () => apiClassEditor(c, Number($('#api-meeting').value), Number($('#api-schedule').value) || 0);
  if ($('#api-btn-schedule-mid')) $('#api-btn-schedule-mid').onclick = () => apiScheduleForm(c, midSchedule || null, 'MID');
  if ($('#api-btn-schedule-uas')) $('#api-btn-schedule-uas').onclick = () => apiScheduleForm(c, uasSchedule || null, 'UAS');
  const workflowIconFor = target => target === 'DIAJUKAN' ? 'send' : target === 'TERVERIFIKASI' ? 'check-circle' : target === 'PERLU_REVISI' ? 'rotate-ccw' : target === 'TERKUNCI' ? 'lock' : 'rotate-ccw';
  schedules.forEach((schedule, index) => {
    const row = document.querySelectorAll('#api-view .api-detail-row')[index]; if (!row) return;
    const actions = (user.role === 'dosen' && page !== 'Hasil Penilaian') || isParticipant || isAssistant3 ? [] : apiWorkflowActions(c, schedule);
    const controls = document.createElement('div'); controls.className = 'row schedule-card-actions';
    actions.forEach(action => { const button = document.createElement('button'); button.type = 'button'; button.className = 'small' + (action.target === 'TERVERIFIKASI' || action.target === 'DIAJUKAN' ? ' primary' : ''); button.innerHTML = `${iconSvg(workflowIconFor(action.target))} <span>${esc(action.label)}</span>`; button.onclick = () => apiWorkflowChange(c, schedule, action.target, action.needsNote); controls.appendChild(button) });
    if (user.role === 'admin' || canEdit) {
      const edit = document.createElement('button');
      edit.type = 'button';
      edit.className = 'small';
      edit.innerHTML = `${iconSvg('edit')} <span>Edit jadwal</span>`;
      const isMid = String(schedule.catatan_workflow || '').includes('MID');
      const isUas = String(schedule.catatan_workflow || '').includes('UAS');
      edit.onclick = () => apiScheduleForm(c, schedule, isMid ? 'MID' : (isUas ? 'UAS' : null));
      controls.appendChild(edit);
    }
    if (['admin', 'asisten'].includes(user.role) && !isParticipant && !isAssistant3) {
      ['xlsx', 'pdf'].forEach(format => { const button = document.createElement('button'); button.type = 'button'; button.className = 'small btn-subtle-teal'; button.innerHTML = `${iconSvg('download')} <span>${format === 'xlsx' ? 'Unduh Excel' : 'Unduh PDF'}</span>`; button.onclick = () => downloadApiReport(classId, Number(schedule.id), format); controls.appendChild(button) });
    }
    if (controls.childElementCount) row.appendChild(controls);
  });
  if (user.role === 'admin' || canEdit) {
    const heading = [...document.querySelectorAll('#api-view .card-head h2')].find(x => x.textContent === 'Jadwal dan penugasan');
    if (heading && !heading.closest('.card-head').querySelector('#api-btn-add-schedule')) {
      const add = document.createElement('button');
      add.type = 'button';
      add.id = 'api-btn-add-schedule';
      add.className = 'small primary';
      add.innerHTML = `${iconSvg('plus')} <span>Tambah jadwal</span>`;
      add.onclick = () => apiScheduleForm(c, null);
      heading.closest('.card-head').appendChild(add);
    }
  }
  const freqActions = document.querySelector('#api-freq-actions');
  if (canManageClassCounts && freqActions) {
    const countButton = document.createElement('button');
    countButton.type = 'button';
    countButton.className = 'small';
    countButton.innerHTML = `${iconSvg('sliders')} <span>Atur komponen nilai akhir</span>`;
    countButton.onclick = () => apiGradeComponentsForm(c);
    freqActions.prepend(countButton);
  }
  if (user.role === 'admin' && freqActions) {
    const edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'small';
    edit.innerHTML = `${iconSvg('edit')} <span>Edit kelompok</span>`;
    edit.onclick = () => apiClassForm(c);
    freqActions.prepend(edit);
  }
  if (canEdit) {
    const attendanceHeading = [...document.querySelectorAll('#api-view .card-head h2')].find(x => x.textContent === 'Presensi mahasiswa');
    if (attendanceHeading) { const sync = document.createElement('button'); sync.type = 'button'; sync.className = 'small'; sync.innerHTML = `${iconSvg('refresh')} <span>Sinkron peserta</span>`; sync.onclick = () => apiSyncParticipants(c); attendanceHeading.parentElement.appendChild(sync) }
  }
  if (user.role === 'admin' && Object.prototype.hasOwnProperty.call(c, 'peserta')) {
    const header = document.querySelector('#api-view [data-api-attendance]')?.closest('table')?.querySelector('thead tr');
    if (header) { const actionHeader = document.createElement('th'); actionHeader.textContent = 'AKSI'; header.appendChild(actionHeader) }
    document.querySelectorAll('#api-view [data-api-attendance]').forEach(input => { const student = c.peserta.find(x => Number(x.id_mahasiswa) === Number(input.dataset.apiAttendance)); if (!student) return; const row = input.closest('tr'), cell = document.createElement('td'), transfer = document.createElement('button'); transfer.type = 'button'; transfer.className = 'small btn-table-action'; transfer.innerHTML = `${iconSvg('repeat')} <span>Pindah</span>`; transfer.onclick = () => apiTransferParticipant(c, student); cell.appendChild(transfer); row.appendChild(cell) })
  }
  if (canEdit) {
    const saveDraft = () => {
      const next = { ...(apiReadAttendanceDraft(draftKey) || {}), attendance: { ...(apiReadAttendanceDraft(draftKey)?.attendance || {}) } };
      document.querySelectorAll('[data-api-attendance]').forEach(el => { if (el.dataset.dirty === '1') { const id = el.dataset.apiAttendance, note = document.querySelector(`[data-api-attendance-note="${id}"]`); next.attendance[id] = { status: el.value || null, catatan: note?.value || null } } });
      document.querySelectorAll('[data-api-attendance-note]').forEach(el => { if (el.dataset.dirty === '1') { const id = el.dataset.apiAttendanceNote, status = document.querySelector(`[data-api-attendance="${id}"]`); next.attendance[id] = { status: status?.value || null, catatan: el.value || null } } });
      if ($('#api-session-date')?.dataset.dirty === '1') next.tanggal_sesi = $('#api-session-date').value;
      if ($('#api-session-material')?.dataset.dirty === '1') next.materi = $('#api-session-material').value;
      const saved = apiWriteAttendanceDraft(draftKey, next); window.apiDraftStorageFailed = !saved; const status = $('#api-draft-status'); if (status) status.textContent = saved ? 'Draft tersimpan di perangkat ini. Browser ini menyimpan perubahan terakhir.' : 'Penyimpanan lokal gagal; jangan muat ulang sebelum perubahan dikirim.';
      const clear = $('#api-clear-draft'); if (clear) clear.hidden = !saved;
    };
    document.querySelectorAll('[data-api-attendance],[data-api-attendance-note],#api-session-date,#api-session-material').forEach(el => ['input', 'change'].forEach(eventName => el.addEventListener(eventName, () => { el.dataset.dirty = '1'; saveDraft() })));
    const attendanceControls = document.querySelector('#api-view [data-api-panel="attendance"] .api-edit-controls');
    if (attendanceControls) {
      const markAllPresent = document.createElement('button');
      markAllPresent.type = 'button';
      markAllPresent.className = 'small btn-subtle-teal';
      markAllPresent.innerHTML = `${iconSvg('user-check')} <span>Hadirkan semua</span>`;
      markAllPresent.disabled = scheduleUnselected || students.length === 0;
      markAllPresent.title = 'Mengubah status semua mahasiswa menjadi Hadir. Perubahan baru dikirim setelah Simpan presensi.';
      markAllPresent.onclick = () => {
        const inputs = [...document.querySelectorAll('#api-view [data-api-attendance]')];
        if (!inputs.length) return;
        if (!confirm(`Atur status ${inputs.length} mahasiswa menjadi Hadir? Status yang sudah dipilih juga akan diubah. Perubahan belum dikirim sampai Anda menekan Simpan presensi.`)) return;
        inputs.forEach(input => { input.value = 'HADIR'; input.dataset.dirty = '1' });
        saveDraft();
        toast(window.apiDraftStorageFailed ? 'Draft gagal disimpan di browser ini. Jangan muat ulang sebelum menyimpan presensi.' : `Status ${inputs.length} mahasiswa diatur menjadi Hadir. Periksa kembali lalu simpan presensi.`);
      };
      attendanceControls.appendChild(markAllPresent);
    }
    if ($('#api-meeting')) $('#api-meeting').onchange = () => apiClassEditor(c, Number($('#api-meeting').value), Number($('#api-schedule')?.value) || scheduleId);
    if ($('#api-schedule')) $('#api-schedule').onchange = () => apiClassEditor(c, Number($('#api-meeting').value), Number($('#api-schedule').value) || 0);
    if ($('#api-clear-draft')) $('#api-clear-draft').onclick = () => { if (!confirm('Hapus draft presensi lokal untuk pertemuan ini?')) return; apiClearAttendanceDraft(draftKey); window.apiDraftStorageFailed = false; apiClassEditor(c, meeting, scheduleId) };
    $('#api-save-attendance').onclick = async () => {
      const button = $('#api-save-attendance'); button.disabled = true;
      try {
        const meetingNumber = Number($('#api-meeting').value), payload = { versi: Number(c.kelompok.versi), pertemuan: { nomor_pertemuan: meetingNumber, tanggal_sesi: $('#api-session-date').value, materi: $('#api-session-material').value }, kehadiran: [] };
        if (schedules.length > 1) { const selected = Number($('#api-schedule').value); if (!selected) throw Error('Pilih jadwal sesi terlebih dahulu.'); payload.pertemuan.id_jadwal_kelas_kuliah = selected }
        if (!payload.pertemuan.tanggal_sesi) throw Error('Tanggal sesi wajib diisi.');
        if (!payload.pertemuan.materi.trim()) throw Error('Materi sesi wajib diisi.');
        const draftNow = apiReadAttendanceDraft(draftKey) || {};
        document.querySelectorAll('[data-api-attendance]').forEach(el => { const id = el.dataset.apiAttendance, note = document.querySelector(`[data-api-attendance-note="${id}"]`), draftRow = draftNow.attendance?.[id]; if (el.dataset.dirty === '1' || note?.dataset.dirty === '1' || draftRow) { payload.kehadiran.push({ id_mahasiswa: Number(id), status: draftRow ? draftRow.status : (el.value || null), catatan: draftRow ? draftRow.catatan : (note?.value || null) }) } });
        if (!payload.kehadiran.length && !$('#api-session-date').dataset.dirty && !$('#api-session-material').dataset.dirty && !draftNow.tanggal_sesi && !draftNow.materi) throw Error('Belum ada perubahan presensi atau sesi untuk disimpan.');
        await api(`/classes/${classId}/changes`, payload, 'PUT');
        apiClearAttendanceDraft(draftKey); window.apiDraftStorageFailed = false; toast('Presensi dan sesi berhasil tersimpan di API pusat.'); await apiClassDetailWithEntry(classId, meetingNumber, scheduleId);
      } catch (e) { toast(e.message); button.disabled = false }
    };
    document.querySelectorAll('[data-api-meeting-grade]').forEach(input => { apiPaintGradeInput(input);['input', 'change'].forEach(eventName => input.addEventListener(eventName, () => apiPaintGradeInput(input))) });
  }
}
function apiDeleteDraftClass(classData) {
  const rosterCount = Array.isArray(classData.peserta) ? classData.peserta.length : 0;
  const modal = dialog(`<form><h2>Hapus kelas Draf</h2><p><strong>${esc(classData.mata_kuliah.kode)} · ${esc(classData.mata_kuliah.nama)}</strong><br>${esc(classData.program_studi.nama)} · Frekuensi ${esc(classData.kelompok.kode)} · ${esc(classData.periode.tahun_akademik)} ${esc(classData.periode.semester)}</p><div class="warning"><strong>Yang akan dihapus:</strong> kelas Praktikum ini, jadwal dan penugasannya, komponen nilai, serta relasi roster/keanggotaan mahasiswa. ${rosterCount} peserta tampil pada roster kelas ini.<br><strong>Yang tetap ada:</strong> master mata kuliah, akun dan master mahasiswa.<br>Penghapusan ditolak jika kelas sudah memiliki sesi, nilai, atau riwayat workflow.</div><label>Alasan penghapusan<textarea name="alasan" rows="3" minlength="10" maxlength="500" required placeholder="Contoh: frekuensi salah saat impor"></textarea></label><label class="api-delete-roster-confirm"><input type="checkbox" name="hapus_roster" required> Saya paham roster kelas dan keanggotaan frekuensi akan dihapus, sementara master mahasiswa tetap ada.</label><div class="row"><button type="button" data-close>Batal</button><button class="small danger" type="submit">${iconSvg('trash')} <span>Hapus kelas dan roster</span></button></div></form>`);
  modal.querySelector('form').onsubmit = async event => { event.preventDefault(); const button = event.currentTarget.querySelector('[type="submit"]'), formData = new FormData(event.currentTarget), alasan = formData.get('alasan'), hapusRoster = formData.get('hapus_roster') === 'on'; button.disabled = true; button.textContent = 'Memeriksa dan menghapus…'; try { const result = await api(`/classes/${Number(classData.id_kelas_kuliah)}`, { versi: Number(classData.kelompok.versi), alasan, hapus_roster: hapusRoster }, 'DELETE'); modal.close(); apiCurrent = null; toast(`Kelas ${classData.kelompok.kode} dihapus. ${Number(result.jumlah_peserta_dihapus || 0)} relasi roster dan ${Number(result.jumlah_anggota_kelompok_dihapus || 0)} keanggotaan dihapus; master mahasiswa tetap ada.`); await apiClassListPage() } catch (error) { toast(error.message); button.disabled = false; button.innerHTML = `${iconSvg('trash')} <span>Hapus kelas dan roster</span>` } };
}
function apiDeleteDraftSchedule(classData, schedule) {
  const weekday = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
  const modal = dialog(`<form><h2>Hapus jadwal draft</h2><p>Jadwal ${esc(classData.kelompok.kode)} · ${esc(weekday[Number(schedule.hari_ke)] || 'Hari')} ${esc(String(schedule.jam_mulai || '').slice(0, 5))}–${esc(String(schedule.jam_selesai || '').slice(0, 5))} akan dihapus. <strong>Kelas dan seluruh peserta tetap disimpan.</strong> API akan menolak tindakan ini jika jadwal memiliki sesi atau riwayat workflow.</p><label>Alasan penghapusan<textarea name="alasan" rows="3" minlength="10" maxlength="500" required placeholder="Contoh: jadwal manual salah ruang"></textarea></label><div class="row"><button type="button" data-close>Batal</button><button class="small danger" type="submit">${iconSvg('trash')} <span>Hapus jadwal draft</span></button></div></form>`);
  modal.querySelector('form').onsubmit = async event => { event.preventDefault(); const button = event.currentTarget.querySelector('[type="submit"]'), alasan = new FormData(event.currentTarget).get('alasan'); button.disabled = true; button.textContent = 'Memeriksa dan menghapus…'; try { await api(`/classes/${Number(classData.id_kelas_kuliah)}/schedules/${Number(schedule.id)}`, { versi: Number(classData.kelompok.versi), alasan }, 'DELETE'); modal.close(); toast('Jadwal dihapus. Kelas dan peserta tetap tersimpan.'); await apiClassDetailWithEntry(Number(classData.id_kelas_kuliah)) } catch (error) { toast(error.message); button.disabled = false; button.innerHTML = `${iconSvg('trash')} <span>Hapus jadwal draft</span>` } };
}
async function apiClassDetailWithEntry(id, selectedMeeting, selectedSchedule, entryTab) {
  if (Number(apiCurrent) !== Number(id)) window.apiDetailTab = entryTab || (user?.role === 'dosen' ? (page === 'Hasil Penilaian' ? 'grading' : 'attendance') : (['admin', 'asisten'].includes(user?.role) ? 'attendance' : 'sessions')); apiCurrent = id; const view = $('#api-view'); view.innerHTML = '<div class="empty">Memuat detail kelas…</div>';
  const lecturerView = user?.role === 'dosen' ? (page === 'Hasil Penilaian' ? 'hasil' : 'aktivitas') : null, viewQuery = lecturerView ? `?dosen_view=${lecturerView}` : '';
  try { const c = await api(`/classes/${id}${viewQuery}`); await apiClassEditor(c, selectedMeeting, selectedSchedule) } catch (e) { apiCurrent = null; view.innerHTML = `<div class="error" role="alert">${esc(e.message)}</div><button class="small btn-back" id="api-back">${iconSvg('arrow-left')} <span>Kembali</span></button>`; $('#api-back').onclick = () => apiClassListPage() }
}
async function downloadApiReport(classId, scheduleId, format) {
  try {
    const response = await fetch(apiUrl(`/classes/${classId}/export?format=${encodeURIComponent(format)}&schedule_id=${scheduleId}`), { credentials: 'same-origin' });
    if (!response.ok) { let message = 'Laporan tidak dapat dibuat.'; try { message = (await response.json()).error || message } catch (e) { } throw Error(message) }
    const blob = await response.blob(), url = URL.createObjectURL(blob), anchor = document.createElement('a'); anchor.href = url; anchor.download = `Praktikum-${classId}-${scheduleId}.${format}`; anchor.click(); setTimeout(() => URL.revokeObjectURL(url), 5000);
  } catch (error) { toast(error.message) }
}
document.addEventListener('click', function (event) { const button = event.target.closest('[data-api-class]'); if (!button || !user?.api_mode) return; event.preventDefault(); event.stopImmediatePropagation(); apiClassDetailWithEntry(Number(button.dataset.apiClass)) }, true);

async function apiAddGroupMember(classId) {
  const classData = await api(`/classes/${classId}`), period = classData.periode || {}, group = classData.kelompok || {};
  const selected = new Map(); let searchSequence = 0, searchTimer;
  const modal = dialog(`<form><h2>Tambah Mahasiswa ke frekuensi</h2><p>${esc(classData.mata_kuliah.kode)} · ${esc(group.kode)} · ${esc(period.tahun_akademik || '')} ${esc(period.semester || '')}</p><label>Cari dengan NIM atau nama<input name="query" minlength="2" maxlength="100" autocomplete="off" required placeholder="Ketik NIM atau nama mahasiswa"></label><div id="api-member-results" class="card-body" aria-live="polite"><p class="help">Pencarian otomatis dimulai setelah mengetik minimal 2 karakter. Centang Mahasiswa yang ingin ditambahkan; pilihan tetap tersimpan saat pencarian berikutnya.</p></div><div id="api-member-selected" class="help">Belum ada Mahasiswa dipilih.</div><p class="help">Tanggal mulai keanggotaan diisi otomatis hari ini (${apiLocalDateToday()}).</p><label>Catatan (opsional)<textarea name="catatan" maxlength="1000"></textarea></label><div class="row"><button type="button" data-close>Batal</button><button class="primary" type="submit" disabled id="api-member-submit">Tambahkan yang dipilih (0)</button></div></form>`);
  const updateSelection = () => { const button = modal.querySelector('#api-member-submit'); button.disabled = selected.size === 0; button.textContent = `Tambahkan yang dipilih (${selected.size})`; modal.querySelector('#api-member-selected').textContent = selected.size ? `${selected.size} Mahasiswa dipilih: ${Array.from(selected.values()).map(student => student.nim).join(', ')}` : 'Belum ada Mahasiswa dipilih.' };
  const renderCandidates = (results, candidates) => { results.innerHTML = candidates.length ? candidates.map(student => `<label class="api-member-choice"><input type="checkbox" data-member="${Number(student.id_mahasiswa)}" ${selected.has(Number(student.id_mahasiswa)) ? 'checked' : ''}><span><strong>${esc(student.nim)}</strong><span>${esc(student.nama || 'Nama belum tersedia')}</span><small>${esc(student.kode_prodi || '')}</small></span></label>`).join('') : '<p class="empty">Tidak ada Mahasiswa yang memenuhi kriteria.</p>'; results.querySelectorAll('[data-member]').forEach(input => input.addEventListener('change', () => { const student = candidates.find(item => Number(item.id_mahasiswa) === Number(input.dataset.member)); if (input.checked) selected.set(Number(student.id_mahasiswa), student); else selected.delete(Number(input.dataset.member)); updateSelection() })) };
  const search = async (query, sequence) => { const results = modal.querySelector('#api-member-results'); try { const candidates = await api(`/classes/${classId}/prospective-participants?query=${encodeURIComponent(query)}`); if (sequence !== searchSequence || !modal.isConnected) return; renderCandidates(results, candidates) } catch (error) { if (sequence === searchSequence && modal.isConnected) results.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>` } };
  modal.querySelector('[name="query"]').addEventListener('input', event => { const query = event.target.value.trim(), sequence = ++searchSequence; clearTimeout(searchTimer); const results = modal.querySelector('#api-member-results'); if (query.length < 2) { results.innerHTML = '<p class="help">Ketik minimal 2 karakter untuk mencari Mahasiswa.</p>'; return } results.innerHTML = '<p class="empty">Mencari Mahasiswa canonical…</p>'; searchTimer = setTimeout(() => search(query, sequence), 300) });
  modal.querySelector('[name="query"]').addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault() });
  modal.querySelector('form').onsubmit = async event => { event.preventDefault(); if (selected.size === 0) return; const button = modal.querySelector('#api-member-submit'), values = Object.fromEntries(new FormData(event.currentTarget)), effectiveDate = apiLocalDateToday(), anggota = Array.from(selected.values()).map(student => ({ nim: student.nim, tanggal_mulai: effectiveDate, catatan: values.catatan || null })); button.disabled = true; button.textContent = 'Memvalidasi pilihan…'; try { const payload = { versi: Number(group.versi), anggota }; const preview = await api(`/classes/${classId}/members/batch`, { ...payload, preview: true }); if (Number(preview.jumlah_konflik) > 0) { const details = (preview.baris || []).filter(row => !row.valid).map(row => `${row.nim}: ${row.keterangan}`).join('\n'); throw Error(`Belum ditambahkan karena ada konflik:\n${details}`) } button.textContent = 'Menambahkan…'; const result = await api(`/classes/${classId}/members/batch`, { ...payload, preview: false }, 'POST'); modal.close(); toast(`${Number(result.jumlah_diimpor)} Mahasiswa berhasil ditambahkan.`); await apiClassDetailWithEntry(classId) } catch (error) { toast(error.message); button.disabled = false; updateSelection() } };
}

const apiRosterObserver = new MutationObserver(() => {
  if (!user?.api_mode || user.role !== 'admin') return;
  const heading = [...document.querySelectorAll('#api-view .card-head h2')].find(item => /^(?:Peserta aktif|Roster peserta|Rekap peserta)/.test(item.textContent.trim()));
  if (!heading) return;
  if (!heading.parentElement.querySelector('#api-add-group-member')) { const button = document.createElement('button'); button.id = 'api-add-group-member'; button.type = 'button'; button.className = 'primary small'; button.innerHTML = `${iconSvg('user-plus')} <span>Tambah Mahasiswa</span>`; button.onclick = () => apiAddGroupMember(apiCurrent); heading.parentElement.append(button) }
  if (!heading.parentElement.querySelector('#api-import-group-members')) { const importButton = document.createElement('button'); importButton.id = 'api-import-group-members'; importButton.type = 'button'; importButton.className = 'small'; importButton.innerHTML = `${iconSvg('upload')} <span>Import XLSX</span>`; importButton.onclick = () => apiImportGroupMembers(apiCurrent); heading.parentElement.append(importButton) }
});
apiRosterObserver.observe(document.body, { childList: true, subtree: true });

async function apiRosterRotationImport() {
  let base64 = null, preview = null;
  const modal = dialog(`<form><h2>Impor roster rotasi</h2><p>Gunakan template roster dari periode aktif. Isi <strong>kelas_tujuan</strong> dari dropdown. Tambah mahasiswa dengan mengisi NIM dan tujuan. Baris yang tidak dicantumkan tidak berubah.</p><p>Penambahan/perpindahan wajib memiliki tanggal efektif. Perpindahan dan rekonsiliasi duplikat juga wajib memiliki alasan. Histori presensi dan nilai di frekuensi asal tetap tersimpan.</p><label>File roster XLSX<input id="api-roster-file" type="file" accept=".xlsx" required></label><div id="api-roster-preview" aria-live="polite"></div><div class="row"><button type="button" data-close>Batal</button><button type="button" id="api-roster-check">Periksa roster</button><button type="button" class="primary" id="api-roster-confirm" disabled>Konfirmasi perubahan</button></div></form>`);
  const filePayload = async () => { const file = modal.querySelector('#api-roster-file').files[0]; if (!file) throw Error('Pilih file roster XLSX terlebih dahulu.'); if (file.size > 4e6) throw Error('Ukuran file maksimal 4 MB.'); return await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result.split(',')[1]); reader.onerror = () => reject(Error('File tidak dapat dibaca.')); reader.readAsDataURL(file) }) };
  modal.querySelector('#api-roster-file').onchange = () => { base64 = null; preview = null; modal.querySelector('#api-roster-preview').innerHTML = ''; modal.querySelector('#api-roster-confirm').disabled = true };
  modal.querySelector('#api-roster-check').onclick = async () => { const button = modal.querySelector('#api-roster-check'), container = modal.querySelector('#api-roster-preview'); button.disabled = true; container.innerHTML = '<p class="empty">Membaca dan memeriksa roster terhadap data canonical periode aktif…</p>'; modal.querySelector('#api-roster-confirm').disabled = true; try { base64 = await filePayload(); preview = await api('/admin/roster-rotation', { base64, preview: true }); const rows = preview.baris || [], statusColor = status => status === 'KONFLIK' ? '#fee2e2' : status === 'PINDAH' || status === 'REKONSILIASI_DUPLIKAT' ? '#ffedd5' : status === 'TAMBAH' ? '#dbeafe' : '#dcfce7'; container.innerHTML = `<div class="card-body"><strong>${Number(preview.jumlah_baris)} baris · ${Number(preview.jumlah_tetap)} tetap · ${Number(preview.jumlah_tambah)} tambah · ${Number(preview.jumlah_pindah)} pindah · ${Number(preview.jumlah_rekonsiliasi_duplikat)} rekonsiliasi · ${Number(preview.jumlah_konflik)} konflik</strong><div class="table-wrap"><table><thead><tr><th>BARIS</th><th>NIM</th><th>NAMA</th><th>FREKUENSI ASAL</th><th>TUJUAN</th><th>HASIL / KONFLIK</th></tr></thead><tbody>${rows.map(row => `<tr style="background:${statusColor(row.status)}"><td>${Number(row.baris)}</td><td>${esc(row.nim || '—')}</td><td>${esc(row.nama || '—')}</td><td>${(row.asal || []).map(item => esc(item.kode_kelompok)).join(', ') || '—'}</td><td>${esc(row.tujuan?.label || 'Tidak dikenali')}</td><td><strong>${esc(row.status)}</strong><br>${(row.alasan_konflik || []).map(esc).join('<br>') || esc(row.keterangan)}</td></tr>`).join('')}</tbody></table></div><small>Periode ${esc(preview.periode?.tahun_akademik || '')} · ${esc(preview.periode?.semester || '')} (${esc(preview.periode?.tanggal_mulai || '—')} – ${esc(preview.periode?.tanggal_selesai || '—')}). Pratinjau belum menyimpan perubahan.</small></div>`; modal.querySelector('#api-roster-confirm').disabled = Number(preview.jumlah_konflik) !== 0 || Number(preview.jumlah_baris) === 0 } catch (error) { container.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>`; preview = null } finally { button.disabled = false } };
  modal.querySelector('#api-roster-confirm').onclick = async () => { const button = modal.querySelector('#api-roster-confirm'); if (!base64 || !preview || Number(preview.jumlah_konflik) > 0) return; const changes = Number(preview.jumlah_tambah) + Number(preview.jumlah_pindah) + Number(preview.jumlah_rekonsiliasi_duplikat); if (!confirm(`Konfirmasi ${changes} perubahan roster? Baris TETAP tidak berubah dan histori nilai/presensi lama tetap tersimpan.`)) return; button.disabled = true; button.textContent = 'Menyimpan batch…'; try { const result = await api('/admin/roster-rotation', { base64, preview: false, versi_kelas: preview.versi_kelas }); modal.close(); toast(`Roster tersimpan. ${Number(result.jumlah_tambah)} ditambah, ${Number(result.jumlah_pindah)} dipindah, ${Number(result.jumlah_rekonsiliasi_duplikat)} duplikat direkonsiliasi.`); await apiClassListPage() } catch (error) { toast(error.message); button.disabled = false; button.textContent = 'Konfirmasi perubahan' } };
}

async function apiImportGroupMembers(classId) {
  const classData = await api(`/classes/${classId}`), period = classData.periode || {}, group = classData.kelompok || {};
  let base64 = null, preview = null;
  const modal = dialog(`<form><h2>Import anggota frekuensi</h2><p>${esc(classData.mata_kuliah.kode)} · ${esc(group.kode)} · ${esc(period.tahun_akademik || '')} ${esc(period.semester || '')}</p><p>File harus berisi NIM Mahasiswa yang sudah ada di master canonical, tanggal mulai eksplisit dalam periode, dan catatan opsional. Kolom master/prodi/akun tidak diimpor.</p><a class="link" href="${esc(apiUrl('/admin/frequency-template'))}" download>Unduh template XLSX</a><label>File XLSX (maksimal 4 MB)<input id="api-member-file" type="file" accept=".xlsx" required></label><div id="api-batch-preview" aria-live="polite"></div><div class="row"><button type="button" data-close>Batal</button><button type="button" id="api-member-preview">Periksa file</button><button type="button" class="primary" id="api-member-commit" disabled>Import anggota</button></div></form>`);
  const filePayload = async () => { const file = modal.querySelector('#api-member-file').files[0]; if (!file) throw Error('Pilih file XLSX terlebih dahulu.'); if (file.size > 4e6) throw Error('Ukuran file maksimal 4 MB.'); return await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result.split(',')[1]); reader.onerror = () => reject(Error('File tidak dapat dibaca.')); reader.readAsDataURL(file) }) };
  modal.querySelector('#api-member-preview').onclick = async () => { const button = modal.querySelector('#api-member-preview'), container = modal.querySelector('#api-batch-preview'); button.disabled = true; container.innerHTML = '<p class="empty">Memeriksa NIM dan tanggal pada API pusat tanpa menyimpan perubahan…</p>'; try { base64 = await filePayload(); preview = await api('/admin/import-frequency', { id_kelas_kuliah: Number(classId), versi: Number(group.versi), base64, preview: true }); container.innerHTML = `<div class="card-body"><strong>${Number(preview.jumlah_valid)} siap · ${Number(preview.jumlah_konflik)} konflik</strong><div class="table-wrap"><table><thead><tr><th>BARIS</th><th>NIM</th><th>NAMA</th><th>HASIL</th></tr></thead><tbody>${(preview.baris || []).map(row => `<tr><td>${Number(row.baris)}</td><td>${esc(row.nim)}</td><td>${esc(row.nama || '—')}</td><td>${row.valid ? 'Siap diimpor' : esc(row.keterangan)}</td></tr>`).join('')}</tbody></table></div><small>Periode canonical: ${esc(period.tanggal_mulai || '—')} – ${esc(period.tanggal_selesai || '—')}. Tanggal mulai harus ditulis sebagai teks YYYY-MM-DD.</small></div>`; modal.querySelector('#api-member-commit').disabled = Number(preview.jumlah_konflik) !== 0 || Number(preview.jumlah_valid) === 0 } catch (error) { container.innerHTML = `<div class="error" role="alert">${esc(error.message)}</div>`; modal.querySelector('#api-member-commit').disabled = true } finally { button.disabled = false } };
  modal.querySelector('#api-member-commit').onclick = async () => { const button = modal.querySelector('#api-member-commit'); if (!base64 || !preview || Number(preview.jumlah_konflik) > 0) return; button.disabled = true; try { const result = await api('/admin/import-frequency', { id_kelas_kuliah: Number(classId), versi: Number(group.versi), base64, preview: false }); modal.close(); toast(`${Number(result.jumlah_diimpor)} Mahasiswa ditambahkan secara atomik.`); await apiClassDetailWithEntry(classId) } catch (error) { toast(error.message); button.disabled = false } };
}
