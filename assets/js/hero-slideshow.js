/* Hero slideshow — standalone on purpose.
   It used to live in main.js, which only runs after every deferred script (GSAP, Swiper,
   tsParticles, ~700 KB) has been downloaded and executed. On slow connections that kept
   the first slide on screen for several seconds. This file is tiny and loaded first. */
(function () {
  'use strict';

  var container = document.getElementById('heroSlideshow');
  if (!container) return;

  var slides = container.querySelectorAll('.hero-slide');
  var dots = document.querySelectorAll('.slideshow-dot');
  if (slides.length < 2) return;

  var INTERVAL = 3500;
  var current = 0;
  var timer = null;
  var paused = false;

  function imageReady(slide) {
    var img = slide.querySelector('img');
    return !img || (img.complete && img.naturalWidth > 0);
  }

  function show(index) {
    slides[current].classList.remove('active');
    if (dots[current]) dots[current].classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    if (dots[current]) dots[current].classList.add('active');
  }

  // Only advance when the next image has arrived, otherwise the slide would fade in empty.
  function next() {
    if (paused) return;
    var target = (current + 1) % slides.length;
    if (imageReady(slides[target])) show(target);
  }

  function start() {
    clearInterval(timer);
    timer = setInterval(next, INTERVAL);
  }

  // Pause only while the tab is hidden (the container covers the whole product area,
  // so pausing on hover froze it whenever the cursor rested on the hero).
  document.addEventListener('visibilitychange', function () { paused = document.hidden; });

  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      show(parseInt(dot.dataset.index, 10));
      start();
    });
  });

  // Fetch the remaining (lazy) slides right after the page has loaded.
  function fetchRest() {
    slides.forEach(function (slide) {
      var img = slide.querySelector('img');
      if (img) img.loading = 'eager';
    });
  }
  if (document.readyState === 'complete') fetchRest();
  else window.addEventListener('load', fetchRest, { once: true });

  start();
})();
