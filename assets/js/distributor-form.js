/* B2B distributor form (home #distribuidores).
 * Posts to the first-party endpoint contacto-distribuidores.php with fetch.
 * The CSRF token, time-trap token and hCaptcha site key are requested from the endpoint
 * when the form is about to be used; hCaptcha is loaded only then (lazy). */
(function () {
  'use strict';
  var form = document.getElementById('dist-form');
  if (!form) return;
  var ENDPOINT = form.getAttribute('action') || 'contacto-distribuidores.php';
  var statusEl = document.getElementById('dist-status');
  var okEl = document.getElementById('dist-success');
  var captchaEl = document.getElementById('dist-captcha');
  var btn = form.querySelector('button[type="submit"]');
  var started = false, widgetId = null, ready = false;

  form.classList.remove('hidden'); // JS available: show the form (no-JS users see the noscript link)

  function say(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.classList.toggle('hidden', !msg);
    statusEl.classList.toggle('text-red-300', !!isError);
    statusEl.classList.toggle('text-green-300', !isError);
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src; s.async = true; s.defer = true;
      s.onload = resolve; s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function start() {
    if (started) return;
    started = true;
    fetch(ENDPOINT + '?token=1', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) throw new Error('token');
        form.elements.csrf_token.value = d.csrf;
        form.elements.form_ts.value = d.ts;
        return loadScript('https://js.hcaptcha.com/1/api.js?render=explicit').then(function () {
          widgetId = window.hcaptcha.render(captchaEl, { sitekey: d.sitekey });
          ready = true;
        });
      })
      .catch(function () {
        started = false;
        say('El formulario no está disponible temporalmente. Escríbenos por WhatsApp.', true);
      });
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      if (entries.some(function (e) { return e.isIntersecting; })) { io.disconnect(); start(); }
    }, { rootMargin: '300px' });
    io.observe(form);
  } else {
    start();
  }
  form.addEventListener('focusin', start);

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!ready) { say('Un momento, estamos preparando el formulario de seguridad. Inténtalo de nuevo en unos segundos.', true); start(); return; }
    var fd = new FormData(form);
    if (!fd.get('h-captcha-response')) { say('Por favor, completa el desafío de seguridad (captcha) antes de enviar.', true); return; }
    btn.disabled = true; say('Enviando...', false);
    fetch(ENDPOINT, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (d) {
        if (d && d.ok) {
          form.classList.add('hidden');
          if (okEl) okEl.classList.remove('hidden');
          say('', false);
        } else {
          say((d && d.message) || 'No pudimos enviar tu solicitud. Inténtalo más tarde o escríbenos por WhatsApp.', true);
          if (window.hcaptcha) window.hcaptcha.reset(widgetId);
          started = false; ready = false; start(); // fresh CSRF/time-trap token for the retry
        }
      })
      .catch(function () {
        say('No pudimos enviar tu solicitud. Revisa tu conexión e inténtalo de nuevo.', true);
        if (window.hcaptcha) window.hcaptcha.reset(widgetId);
      })
      .then(function () { btn.disabled = false; });
  });
})();
