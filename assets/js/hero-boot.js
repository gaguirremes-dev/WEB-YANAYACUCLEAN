/* Hero boot: marks the page as "motion-ready" before first paint so the entrance can start from a
   hidden state. hero.js removes the class when it is done; a CSS failsafe reveals everything if
   it never runs. Skipped entirely for prefers-reduced-motion. */
(function () {
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduce) document.documentElement.classList.add('yx-js');
})();
