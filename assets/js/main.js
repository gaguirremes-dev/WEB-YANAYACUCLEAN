/* =============================================
   YanaYacu Clean — main.js
   ============================================= */

document.addEventListener('DOMContentLoaded', () => {

  /* ---- 0. Non-blocking stylesheets (media="print" swap, no inline handler) ---- */
  document.querySelectorAll('link[data-async-css]').forEach(l => { l.media = 'all'; });

  /* ---- 1. GSAP + ScrollTrigger ---- */
  gsap.registerPlugin(ScrollTrigger);

  // Hero entrance + slideshow live in assets/js/hero.js (Motion), not here.

  // Reveal sections on scroll
  document.querySelectorAll('.reveal-section').forEach(section => {
    const items = section.querySelectorAll('.reveal-item');
    if (!items.length) return;
    gsap.fromTo(items,
      { opacity: 0, y: 44 },
      {
        opacity: 1, y: 0,
        duration: 0.65,
        stagger: 0.13,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: section,
          start: 'top 82%',
        }
      }
    );
  });

  // Stat counters
  document.querySelectorAll('.counter').forEach(el => {
    const target = parseInt(el.getAttribute('data-target'), 10);
    gsap.fromTo({ val: 0 }, { val: target }, {
      duration: 2,
      ease: 'power2.out',
      onUpdate() { el.textContent = Math.round(this.targets()[0].val).toLocaleString('es-PE'); },
      scrollTrigger: { trigger: el, start: 'top 85%', once: true }
    });
  });

  /* ---- 2. tsParticles: removed with the 2026 hero redesign (no particles) ---- */

  /* ---- 3. Swiper — Catálogo B2C ---- */
  const swiperOptions = {
    slidesPerView: 1.15,
    spaceBetween: 16,
    centeredSlides: false,
    pagination: { el: '.swiper-pagination', clickable: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    breakpoints: {
      640:  { slidesPerView: 2,   spaceBetween: 20 },
      1024: { slidesPerView: 3,   spaceBetween: 24 },
    },
  };

  // Se inicializan al cambiar tab via Alpine
  window.initSwiper = (id) => {
    const el = document.getElementById(id);
    if (el && !el.swiper) {
      new Swiper(el, { ...swiperOptions });
    }
  };

  // Testimonios Swiper
  new Swiper('#swiper-testimonios', {
    slidesPerView: 1,
    spaceBetween: 24,
    loop: true,
    autoplay: { delay: 4500, disableOnInteraction: false },
    pagination: { el: '#testimonios-pagination', clickable: true },
    breakpoints: {
      768:  { slidesPerView: 2 },
      1024: { slidesPerView: 3 },
    },
  });

  /* ---- 4. Navbar shrink on scroll ---- */
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 80) {
      navbar.classList.add('shadow-xl', 'py-2');
      navbar.classList.remove('py-4');
    } else {
      navbar.classList.remove('shadow-xl', 'py-2');
      navbar.classList.add('py-4');
    }
  });

  /* ---- 5. Catalog PDF: click-to-load ---- */
  (function initCatalogViewer() {
    const btn    = document.getElementById('catalogLoadBtn');
    const poster = document.getElementById('catalogPoster');
    const frame  = document.getElementById('catalogFrame');
    if (!btn || !poster || !frame) return;
    btn.addEventListener('click', () => {
      frame.src = frame.dataset.src;
      frame.hidden = false;
      poster.hidden = true;
    });
  })();

  /* ---- 6. Accordion legal ---- */
  document.querySelectorAll('.accordion-trigger').forEach(btn => {
    btn.addEventListener('click', () => {
      const body   = btn.nextElementSibling;
      const icon   = btn.querySelector('.accordion-icon');
      const isOpen = body.classList.contains('open');

      document.querySelectorAll('.accordion-body').forEach(b => b.classList.remove('open'));
      document.querySelectorAll('.accordion-icon').forEach(i => {
        i.style.transform = 'rotate(0deg)';
      });

      if (!isOpen) {
        body.classList.add('open');
        icon.style.transform = 'rotate(180deg)';
      }
    });
  });

  /* ---- 7. Smooth scroll para links de nav ---- */
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  /* ---- 8. Hero Slideshow: moved to assets/js/hero-slideshow.js (loads first, no GSAP dependency) ---- */

});
