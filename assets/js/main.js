/* =============================================
   YanaYacu Clean — main.js
   ============================================= */

document.addEventListener('DOMContentLoaded', () => {

  /* ---- 1. GSAP + ScrollTrigger ---- */
  gsap.registerPlugin(ScrollTrigger);

  // Hero entrance
  const heroTl = gsap.timeline({ delay: 0.2 });
  heroTl
    .from('.hero-badge',    { opacity: 0, y: -20, duration: 0.5, ease: 'power2.out' })
    .from('.hero-title',    { opacity: 0, y: 60,  duration: 0.8, ease: 'power3.out' }, '-=0.2')
    .from('.hero-subtitle', { opacity: 0, y: 30,  duration: 0.6, ease: 'power2.out' }, '-=0.5')
    .from('.hero-ctas',     { opacity: 0, y: 20,  duration: 0.5, ease: 'power2.out' }, '-=0.3')
    .from('.hero-stats',    { opacity: 0, y: 20,  duration: 0.5, ease: 'power2.out' }, '-=0.2')
    .from('.hero-product',  { opacity: 0, x: 60,  duration: 0.9, ease: 'power3.out' }, '-=1.0');

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

  /* ---- 2. tsParticles (hero) ---- */
  if (typeof tsParticles !== 'undefined') {
    tsParticles.load('tsparticles', {
      fullScreen: { enable: false },
      background: { color: { value: 'transparent' } },
      fpsLimit: 60,
      particles: {
        number: { value: 55, density: { enable: true, area: 900 } },
        color: { value: ['#4DA6D6', '#7EC8E3', '#FFFFFF'] },
        shape: { type: 'circle' },
        opacity: { value: { min: 0.1, max: 0.45 }, animation: { enable: true, speed: 0.6 } },
        size:    { value: { min: 2, max: 6 }, animation: { enable: true, speed: 2 } },
        move: {
          enable: true,
          speed: 1.2,
          direction: 'top',
          random: true,
          straight: false,
          outModes: { default: 'out' },
        },
        links: { enable: false },
      },
      interactivity: {
        events: {
          onHover: { enable: true, mode: 'repulse' },
          resize: true,
        },
        modes: { repulse: { distance: 80, duration: 0.4 } },
      },
      detectRetina: true,
    });
  }

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

  /* ---- 5. Lucide icons ---- */
  if (typeof lucide !== 'undefined') lucide.createIcons();

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

});
