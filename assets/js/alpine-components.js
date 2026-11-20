/* Alpine components for the CSP build (@alpinejs/csp): no inline expressions in the markup.
 * Must load BEFORE assets/vendor/alpine.min.js (both deferred, in this order). */
document.addEventListener('alpine:init', function () {
  Alpine.data('navbar', function () {
    return {
      menuOpen: false,
      toggle: function () { this.menuOpen = !this.menuOpen; },
      close: function () { this.menuOpen = false; },
      get menuClosed() { return !this.menuOpen; },
      get expanded() { return this.menuOpen ? 'true' : 'false'; }
    };
  });
});
