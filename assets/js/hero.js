/* =============================================
   YanaYacu Clean - hero.js
   Hero entrance, product slideshow, idle float, parallax and button springs.
   - Loads right after motion.min.js (Motion = vanilla framer-motion) and deliberately depends on
     neither GSAP nor Swiper: the slideshow must start as soon as this file runs.
   - Without Motion (or with prefers-reduced-motion) the hero is static and complete; the slideshow
     still advances unless the user asked for reduced motion.
   ============================================= */
(function () {
  'use strict';

  var hero = document.getElementById('inicio');
  var stage = document.getElementById('yxStage');
  if (!hero || !stage) return;

  var root = document.documentElement;
  var M = window.Motion;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var canAnimate = !!(M && M.animate) && !reduce;
  var animate = canAnimate ? M.animate : null;

  var EASE_OUT = [0.22, 1, 0.36, 1];
  var SPRING = { type: 'spring', duration: 0.45, bounce: 0.18 };

  function safe(fn) { try { return fn(); } catch (e) { return null; } }

  function settle() {
    hero.classList.add('yx-done');
    root.classList.remove('yx-js');
  }

  /* ---------- 1. Entrance (title lines, lead, buttons, swoosh) ---------- */
  function entrance() {
    if (!canAnimate) { settle(); return; }
    var items = hero.querySelectorAll('.yx-rv');
    var pending = [];
    items.forEach(function (el, i) {
      var isTitle = el.classList.contains('yx-title__line');
      var kf = {
        opacity: [0, 1],
        transform: ['translateY(' + (isTitle ? 26 : 16) + 'px)', 'translateY(0px)'],
      };
      if (isTitle) kf.filter = ['blur(8px)', 'blur(0px)'];
      pending.push(animate(el, kf, { duration: isTitle ? 0.8 : 0.6, delay: 0.05 + i * 0.09, ease: EASE_OUT }).finished);
    });
    var swoosh = hero.querySelector('.yx-swoosh');
    if (swoosh) {
      /* Wipe reveal, left to right, of the filled swoosh shape. */
      pending.push(animate(swoosh, { clipPath: ['inset(0 100% 0 0)', 'inset(0 0% 0 0)'] }, { duration: 0.8, delay: 0.7, ease: [0.65, 0, 0.35, 1] }).finished);
    }
    Promise.all(pending).then(settle, settle);
  }

  /* ---------- 2. Slideshow ---------- */
  var slides = Array.prototype.slice.call(stage.querySelectorAll('.yx-slide'));
  var INTERVAL = 3800;
  var current = 0;
  var busy = false;
  var hidden = document.hidden;
  var timer = null;

  if (canAnimate) stage.classList.add('yx-motion');

  function ready(img) { return img.complete && img.naturalWidth > 0; }

  function show(index) {
    var prev = slides[current];
    var next = slides[index];
    if (!next || next === prev) return;
    current = index;

    next.classList.add('is-active');
    next.removeAttribute('aria-hidden');
    prev.classList.remove('is-active');
    prev.setAttribute('aria-hidden', 'true');

    if (!canAnimate) return;
    busy = true;
    prev.classList.add('is-leaving');
    var done = function () { prev.classList.remove('is-leaving'); busy = false; };
    animate(prev, { opacity: [1, 0], transform: ['translateY(0px) scale(1)', 'translateY(10px) scale(0.985)'] }, { duration: 0.42, ease: 'easeIn' })
      .finished.then(done, done);
    animate(next, { opacity: [0, 1], transform: ['translateY(18px) scale(0.96)', 'translateY(0px) scale(1)'] }, { duration: 0.75, delay: 0.12, ease: EASE_OUT });
  }

  function schedule(ms) { clearTimeout(timer); timer = setTimeout(tick, ms); }

  // Only advance when the next image has arrived and is decoded; otherwise retry shortly.
  function tick() {
    if (hidden || busy) { schedule(600); return; }
    var target = (current + 1) % slides.length;
    var img = slides[target];
    if (!ready(img)) { img.loading = 'eager'; schedule(500); return; }
    var go = function () { show(target); schedule(INTERVAL); };
    if (img.decode) img.decode().then(go, go); else go();
  }

  // Pause only while the tab is hidden (never on hover).
  document.addEventListener('visibilitychange', function () { hidden = document.hidden; });

  function startSlideshow() {
    if (slides.length < 2 || reduce) return;
    schedule(INTERVAL);
    // Fetch the remaining (lazy) slides once the page has loaded.
    var fetchRest = function () { slides.forEach(function (s) { s.loading = 'eager'; }); };
    if (document.readyState === 'complete') fetchRest();
    else window.addEventListener('load', fetchRest, { once: true });
  }

  /* ---------- 3. Idle float (bottle) + inverse contact shadow ---------- */
  function idleFloat() {
    if (!canAnimate) return;
    var float = stage.querySelector('.yx-stage__float');
    var shadow = stage.querySelector('.yx-stage__shadow');
    var loop = { duration: 3.2, repeat: Infinity, repeatType: 'mirror', ease: 'easeInOut' };
    safe(function () { animate(float, { transform: ['translateY(0px)', 'translateY(-6px)'] }, loop); });
    safe(function () { animate(shadow, { transform: ['scale(1)', 'scale(0.93)'], opacity: [1, 0.72] }, loop); });
  }

  /* ---------- 4. Subtle parallax (mouse on fine pointers, scroll everywhere) ---------- */
  function parallax() {
    if (!canAnimate) return;
    var bgWrap = hero.querySelector('.yx-hero__bg');
    var bgImg = bgWrap && bgWrap.querySelector('img');
    if (!bgWrap || !bgImg) return;

    var ticking = false;
    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        var y = window.scrollY || 0;
        if (y < hero.offsetHeight) bgWrap.style.transform = 'translate3d(0,' + Math.min(y * 0.06, 28).toFixed(1) + 'px,0)';
        ticking = false;
      });
    }, { passive: true });

    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    var last = 0;
    var move = function (nx, ny) {
      safe(function () { animate(stage, { transform: 'translate3d(' + (nx * -7).toFixed(1) + 'px,' + (ny * -4).toFixed(1) + 'px,0)' }, { duration: 0.9, ease: EASE_OUT }); });
      safe(function () { animate(bgImg, { transform: 'translate3d(' + (nx * 6).toFixed(1) + 'px,' + (ny * 4).toFixed(1) + 'px,0)' }, { duration: 1.1, ease: EASE_OUT }); });
    };
    hero.addEventListener('pointermove', function (e) {
      var now = performance.now();
      if (now - last < 90) return;
      last = now;
      var r = hero.getBoundingClientRect();
      move((e.clientX - r.left) / r.width * 2 - 1, (e.clientY - r.top) / r.height * 2 - 1);
    }, { passive: true });
    hero.addEventListener('pointerleave', function () { move(0, 0); });
  }

  /* ---------- 5. Button springs ---------- */
  function buttons() {
    if (!canAnimate || !M.hover || !M.press) return;
    hero.querySelectorAll('.yx-btn').forEach(function (btn) {
      M.hover(btn, function () {
        animate(btn, { transform: 'translateY(-2px) scale(1.02)' }, SPRING);
        return function () { animate(btn, { transform: 'translateY(0px) scale(1)' }, SPRING); };
      });
      M.press(btn, function () {
        animate(btn, { transform: 'scale(0.97)' }, { type: 'spring', duration: 0.25, bounce: 0 });
        return function () { animate(btn, { transform: 'translateY(0px) scale(1)' }, SPRING); };
      });
    });
  }

  entrance();
  startSlideshow();
  idleFloat();
  parallax();
  buttons();
})();
