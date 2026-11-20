/* Navbar shadow/shrink on scroll for the landing pages (the home page does this in main.js). */
(function () {
  var navbar = document.getElementById('navbar');
  if (!navbar) return;
  window.addEventListener('scroll', function () {
    var scrolled = window.scrollY > 80;
    navbar.classList.toggle('shadow-xl', scrolled);
    navbar.classList.toggle('py-2', scrolled);
    navbar.classList.toggle('py-4', !scrolled);
  }, { passive: true });
})();
