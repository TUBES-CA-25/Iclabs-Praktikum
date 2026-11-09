/* Intro ICLabs: satu putaran, lalu mendarat pada logo halaman yang masih aktif. */
(function () {
  'use strict';
  const assetBase = new URL('.', document.currentScript.src);
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let started = false;
  let observer;

  function findDestination() {
    return [...document.querySelectorAll('.auth-brand img, .brand img.brand-logo')].find(image => {
      const r = image.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && r.left >= 0 && r.top >= 0 &&
        r.right <= window.innerWidth && r.bottom <= window.innerHeight;
    });
  }

  function loadViewer() {
    if (window.customElements?.get('model-viewer')) return Promise.resolve(true);
    if (!window.customElements) return Promise.resolve(false);
    return new Promise(resolve => {
      const script = document.createElement('script');
      script.type = 'module';
      script.src = new URL('model-viewer.min.js', assetBase).href;
      script.onload = () => resolve(Boolean(customElements.get('model-viewer')));
      script.onerror = () => resolve(false);
      document.head.append(script);
    });
  }

  function start() {
    if (started || motion.matches || document.hidden || !Element.prototype.animate) return;
    if (!findDestination()) return;
    started = true;
    observer?.disconnect();

    let stopped = false;
    let markedTarget;
    let frameId;
    let deadline;
    const timers = new Map();
    const animations = new Set();
    const listeners = new AbortController();
    const root = document.documentElement;
    const layer = document.createElement('div');
    layer.className = 'iclabs-intro-overlay';
    layer.dataset.phase = 'loading';
    layer.innerHTML = '<div class="iclabs-intro-backdrop" aria-hidden="true"></div>' +
      '<div class="iclabs-intro-stage" aria-hidden="true"><div class="iclabs-intro-logo">' +
      '<img class="iclabs-intro-fallback" alt=""><img class="iclabs-intro-landing" alt="">' +
      '</div><div class="iclabs-intro-name"><strong>ICLabs</strong><span>Praktikum · FIKOM UMI</span></div></div>' +
      '<button type="button" class="iclabs-intro-skip" aria-label="Lewati animasi pembuka">Lewati intro <span aria-hidden="true">↗</span></button>';
    const logo = layer.querySelector('.iclabs-intro-logo');
    const fallback = layer.querySelector('.iclabs-intro-fallback');
    const landing = layer.querySelector('.iclabs-intro-landing');
    const backdrop = layer.querySelector('.iclabs-intro-backdrop');
    const name = layer.querySelector('.iclabs-intro-name');
    const skip = layer.querySelector('button');
    fallback.src = new URL('logo-iclabs.jpg', assetBase).href;
    const model = document.createElement('model-viewer');
    const attributes = {
      src: new URL('iclabs-logo-3d.glb', assetBase).href, alt: '',
      'environment-image': 'neutral', exposure: '1.1', 'shadow-intensity': '0',
      'interaction-prompt': 'none', 'camera-orbit': '0deg 90deg auto',
      orientation: '0deg 0deg -300deg', loading: 'eager', tabindex: '-1'
    };
    for (const [key, value] of Object.entries(attributes)) model.setAttribute(key, value);
    logo.prepend(model);

    function wait(ms) {
      return new Promise(resolve => {
        const id = setTimeout(() => { timers.delete(id); resolve(); }, ms);
        timers.set(id, resolve);
      });
    }
    function animate(element, frames, duration, easing = 'cubic-bezier(.22,1,.36,1)') {
      if (stopped) return Promise.resolve();
      const animation = element.animate(frames, { duration, easing, fill: 'forwards' });
      animations.add(animation);
      return animation.finished.catch(() => {});
    }
    function finish() {
      if (stopped) return;
      stopped = true;
      clearTimeout(deadline);
      cancelAnimationFrame(frameId);
      for (const [id, resolve] of timers) { clearTimeout(id); resolve(); }
      timers.clear();
      for (const animation of animations) animation.cancel();
      animations.clear();
      markedTarget?.removeAttribute('data-intro-destination');
      root.classList.remove('iclabs-intro-active');
      listeners.abort();
      const restoreFocus = document.activeElement === skip;
      layer.remove();
      if (restoreFocus) document.querySelector('#login-email, .auth-form-panel input:not([hidden]), .auth-form-panel a, .nav button')?.focus({ preventScroll: true });
    }
    function targetNow() {
      const target = findDestination();
      if (!target) return null;
      if (markedTarget !== target) {
        markedTarget?.removeAttribute('data-intro-destination');
        target.setAttribute('data-intro-destination', '');
        markedTarget = target;
      }
      return target;
    }
    function frames(duration, draw) {
      return new Promise(resolve => {
        const begun = performance.now();
        function tick(now) {
          if (stopped) return resolve();
          const progress = Math.min(1, (now - begun) / duration);
          draw(progress);
          if (progress < 1 && !stopped) frameId = requestAnimationFrame(tick);
          else resolve();
        }
        frameId = requestAnimationFrame(tick);
        // Resolve even if an interaction cancels the pending frame.
        listeners.signal.addEventListener('abort', () => resolve(), { once: true });
      });
    }
    function matchLanding(target, size) {
      const style = getComputedStyle(target);
      const ratio = size / target.getBoundingClientRect().width;
      landing.src = target.currentSrc || target.src;
      for (const key of ['backgroundColor', 'objectFit', 'objectPosition', 'borderStyle', 'borderColor']) {
        landing.style[key] = style[key];
      }
      for (const key of ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'borderTopLeftRadius', 'borderTopRightRadius', 'borderBottomLeftRadius', 'borderBottomRightRadius']) {
        landing.style[key] = `${parseFloat(style[key]) * ratio}px`;
      }
    }

    async function play() {
      // Listen before defining the custom element so early load errors cannot be missed.
      const modelEvent = new Promise(resolve => {
        model.addEventListener('load', () => resolve(true), { once: true, signal: listeners.signal });
        model.addEventListener('error', () => resolve(false), { once: true, signal: listeners.signal });
      });
      const ready = loadViewer().then(available => available && (model.loaded || modelEvent));
      const useModel = await Promise.race([ready.catch(() => false), wait(900).then(() => false)]);
      if (stopped) return;
      layer.dataset.renderer = useModel ? 'model' : 'image';
      if (useModel) {
        model.style.opacity = '1';
        await animate(fallback, [{ opacity: 1 }, { opacity: 0 }], 120, 'linear');
      } else model.remove();
      if (stopped) return;
      layer.dataset.phase = 'turn';
      await frames(920, t => {
        const eased = 1 - Math.pow(1 - t, 3);
        if (useModel) model.orientation = `0deg 0deg ${-300 * (1 - eased)}deg`;
        else fallback.style.transform = `perspective(800px) rotateY(${-300 * (1 - eased)}deg)`;
      });
      await wait(100);
      if (stopped) return;

      // The application can replace the login DOM after /me returns 401.
      // Resolve the current logo on every frame instead of retaining a detached node.
      const target = targetNow();
      if (!target) return finish();
      const source = logo.getBoundingClientRect();
      matchLanding(target, source.width);
      layer.dataset.phase = 'flight';
      animate(name, [{ opacity: 1 }, { opacity: 0 }], 180, 'ease-out');
      animate(skip, [{ opacity: 1 }, { opacity: 0 }], 180, 'linear');
      await frames(820, t => {
        const current = targetNow();
        if (!current) return finish();
        const r = current.getBoundingClientRect();
        const eased = t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        const dx = r.left + r.width / 2 - (source.left + source.width / 2);
        const dy = r.top + r.height / 2 - (source.top + source.height / 2);
        const arc = Math.sin(Math.PI * t) * Math.min(36, Math.abs(dy) * .12);
        const sx = 1 + (r.width / source.width - 1) * eased;
        const sy = 1 + (r.height / source.height - 1) * eased;
        logo.style.transform = `translate(${dx * eased}px, ${dy * eased - arc}px) scale(${sx}, ${sy})`;
        const blend = Math.max(0, Math.min(1, (t - .62) / .3));
        landing.style.opacity = String(blend);
        (useModel ? model : fallback).style.opacity = String(1 - blend);
        backdrop.style.opacity = String(1 - Math.min(1, t / .8));
      });
      if (stopped) return;
      layer.dataset.phase = 'landed';
      markedTarget?.removeAttribute('data-intro-destination');
      await animate(logo, [{ opacity: 1 }, { opacity: 0 }], 100, 'linear');
      finish();
    }

    document.body.append(layer);
    root.classList.add('iclabs-intro-active');
    targetNow();
    const options = { signal: listeners.signal };
    skip.addEventListener('click', finish, options);
    document.addEventListener('pointerdown', finish, { ...options, capture: true });
    document.addEventListener('keydown', e => { if (['Escape', 'Tab'].includes(e.key)) finish(); }, options);
    document.addEventListener('focusin', e => { if (!layer.contains(e.target)) finish(); }, options);
    window.addEventListener('resize', finish, options);
    window.addEventListener('pagehide', finish, options);
    document.addEventListener('visibilitychange', () => { if (document.hidden) finish(); }, options);
    motion.addEventListener('change', () => { if (motion.matches) finish(); }, options);
    deadline = setTimeout(finish, 5000);
    play().catch(finish);
  }

  window.startIclabsIntro = start;
  if (!motion.matches) {
    observer = new MutationObserver(() => { if (findDestination()) start(); });
    observer.observe(document.body, { childList: true, subtree: true });
    start();
    window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
  }
})();
