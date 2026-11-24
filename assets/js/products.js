/* Product explorer (photo selectors + presentation toggle) and video strip.
 * Plain DOM, no dependencies, CSP-safe (external file, no inline handlers). Markup comes from
 * scripts/render-products.js. Without JS the first product stays visible and the videos have controls-less posters. */
(function () {
  'use strict';

  /* ---------- Product explorer ---------- */
  document.querySelectorAll('[data-explorer]').forEach(function (root) {
    var tabs = root.querySelectorAll('.yxp__tab');
    var panels = root.querySelectorAll('[data-panel]');

    function select(id) {
      tabs.forEach(function (t) { t.setAttribute('aria-pressed', t.dataset.target === id ? 'true' : 'false'); });
      panels.forEach(function (p) { p.hidden = p.dataset.panel !== id; });
      /* Keep the active thumbnail visible in the (touch-scrollable) selector row. */
      tabs.forEach(function (t) {
        if (t.dataset.target !== id) return;
        var row = t.parentNode;
        var left = t.offsetLeft - (row.clientWidth - t.offsetWidth) / 2;
        if (row.scrollTo) row.scrollTo({ left: left, behavior: 'smooth' }); else row.scrollLeft = left;
      });
    }
    function step(dir) {
      var cur = 0;
      tabs.forEach(function (t, i) { if (t.getAttribute('aria-pressed') === 'true') cur = i; });
      select(tabs[(cur + dir + tabs.length) % tabs.length].dataset.target);
    }
    /* Mouse drag-to-scroll on the selector row (touch already scrolls natively). */
    (function () {
      var row = tabs.length ? tabs[0].parentNode : null;
      if (!row) return;
      var down = false, moved = false, startX = 0, startLeft = 0;
      row.addEventListener('dragstart', function (e) { e.preventDefault(); });
      row.addEventListener('pointerdown', function (e) {
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        down = true; moved = false; startX = e.clientX; startLeft = row.scrollLeft;
      });
      window.addEventListener('pointermove', function (e) {
        if (!down) return;
        var dx = e.clientX - startX;
        if (Math.abs(dx) > 5) moved = true;
        if (moved) row.scrollLeft = startLeft - dx;
      });
      window.addEventListener('pointerup', function () { down = false; });
      row.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
    })();
    /* Swipe left/right on the product area to move to the next/previous product. */
    var area = root.querySelector('.yxp__panels');
    if (area) {
      var sx = 0, sy = 0, tracking = false;
      area.addEventListener('touchstart', function (e) {
        if (e.touches.length !== 1) { tracking = false; return; }
        sx = e.touches[0].clientX; sy = e.touches[0].clientY; tracking = true;
      }, { passive: true });
      area.addEventListener('touchend', function (e) {
        if (!tracking) return;
        tracking = false;
        var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.6) step(dx < 0 ? 1 : -1);
      }, { passive: true });
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(t.dataset.target); });
      /* Arrow keys move between selectors (roving focus), Enter/Space select (native button). */
      t.addEventListener('keydown', function (e) {
        var step = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (!step) return;
        e.preventDefault();
        var next = tabs[(i + step + tabs.length) % tabs.length];
        next.focus();
        select(next.dataset.target);
      });
    });

    panels.forEach(function (panel) {
      var buttons = panel.querySelectorAll('[data-variant]');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var key = btn.dataset.variant;
          buttons.forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
          panel.querySelectorAll('[data-variant-show]').forEach(function (el) { el.hidden = el.dataset.variantShow !== key; });
        });
      });
    });
  });

  /* ---------- Videos: autoplay (muted, looped) while visible, pause when off-screen. No controls.
     Self-healing: a failed play(), a browser-initiated pause or a stalled load is retried while the video is on screen. ---------- */
  var frames = Array.prototype.slice.call(document.querySelectorAll('[data-video]'));
  if (!frames.length) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce || !('IntersectionObserver' in window)) return; /* stays on the poster */

  var vids = frames.map(function (f) { var v = f.querySelector('video'); v.muted = true; v.loop = true; return v; });
  var visible = new Set();

  function ensure(v) {
    if (!visible.has(v) || document.hidden || !v.paused || v.ended) return;
    var p = v.play();
    if (p && p.catch) p.catch(function () { /* will be retried by canplay / the watchdog */ });
  }

  vids.forEach(function (v) {
    ['canplay', 'loadeddata'].forEach(function (e) { v.addEventListener(e, function () { ensure(v); }); });
    v.addEventListener('pause', function () { setTimeout(function () { ensure(v); }, 200); });
    v.addEventListener('stalled', function () { if (visible.has(v) && v.readyState < 2) v.load(); });
    v.addEventListener('error', function () { if (visible.has(v)) setTimeout(function () { v.load(); ensure(v); }, 1500); });
  });

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      var v = en.target.querySelector('video');
      if (en.isIntersecting) { visible.add(v); ensure(v); }
      else { visible.delete(v); v.pause(); }
    });
  }, { threshold: 0.25 });
  frames.forEach(function (f) { io.observe(f); });

  /* Watchdog: cheap safety net (only touches videos that should be playing but are not). */
  setInterval(function () { visible.forEach(ensure); }, 1200);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) visible.forEach(ensure); });
})();
