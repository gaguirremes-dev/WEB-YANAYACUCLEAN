'use strict';
// ==========================================
//  SHOWCASE ORBITAL — YanaYacu Clean
//  Productos rotando alrededor del escenario
// ==========================================
(function () {
  const stage = document.getElementById('yana-stage');
  const orbit = document.getElementById('yana-orbit');
  if (!stage || !orbit) return;

  const prods = Array.from(orbit.querySelectorAll('.yana-orbital-prod'));
  const N = prods.length;
  if (!N) return;

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let Rx = 320, Ry = 44, baseY = -160;
  function measure() {
    const w = stage.clientWidth;
    const h = stage.clientHeight || 600;
    Rx = Math.min(w * 0.38, 450);
    Ry = Math.min(h * 0.09, 72);
  }
  measure();
  window.addEventListener('resize', measure, { passive: true });

  const idleSpeed = reduce ? 0 : 0.14;
  let idleAngle = 0;
  let frontEl = null;

  function render() {
    const base = idleAngle;
    let bestCos = -2, best = null;

    for (let i = 0; i < N; i++) {
      const a     = base + i * (Math.PI * 2 / N);
      const cos   = Math.cos(a);
      const sin   = Math.sin(a);
      const depth = (cos + 1) / 2;
      const bob   = reduce ? 0 : Math.sin(idleAngle * 1.25 + i * 1.3) * 7;

      const x     = sin * Rx;
      const y     = baseY + cos * Ry + bob;
      const scale = 0.60 + depth * 0.43;

      const el = prods[i];
      el.style.transform = `translate(calc(-50% + ${x.toFixed(1)}px), calc(-50% + ${y.toFixed(1)}px)) scale(${scale.toFixed(3)})`;
      el.style.opacity   = (0.42 + depth * 0.58).toFixed(3);
      el.style.zIndex    = String(30 + Math.round(cos * 22));
      el.style.filter    = cos < 0 ? `blur(${(-cos * 1.1).toFixed(2)}px)` : 'none';

      if (cos > bestCos) { bestCos = cos; best = el; }
    }

    if (best !== frontEl) {
      if (frontEl) frontEl.classList.remove('is-front');
      best.classList.add('is-front');
      frontEl = best;
    }
  }

  // Parallax sutil del mouse sobre el personaje central
  let mx = 0, my = 0;
  const character = document.getElementById('yana-character');
  if (!reduce && character) {
    stage.addEventListener('mousemove', e => {
      const r = stage.getBoundingClientRect();
      mx = (e.clientX - r.left) / r.width  - 0.5;
      my = (e.clientY - r.top)  / r.height - 0.5;
    }, { passive: true });
    stage.addEventListener('mouseleave', () => { mx = 0; my = 0; });
  }

  // Render estático si no hay GSAP
  if (!window.gsap) { render(); return; }

  let lastT = gsap.ticker.time;
  gsap.ticker.add(() => {
    const t  = gsap.ticker.time;
    const dt = t - lastT; lastT = t;
    idleAngle += dt * idleSpeed;
    render();
    if (character && !reduce) {
      character.style.translate = `${(mx * 14).toFixed(1)}px ${(my * 10).toFixed(1)}px`;
    }
  });
})();
