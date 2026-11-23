/* Alpine components for the CSP build (@alpinejs/csp): no inline expressions in the markup.
 * Must load BEFORE assets/vendor/alpine.min.js (both deferred, in this order). */
document.addEventListener('alpine:init', function () {
  Alpine.data('navbar', function () {
    return {
      menuOpen: false,
      toggle: function () { this.menuOpen = !this.menuOpen; },
      close: function () { this.menuOpen = false; },
      /* Same as close, but reachable from nested x-data scopes that define their own `close`. */
      closeMenu: function () { this.menuOpen = false; },
      get menuClosed() { return !this.menuOpen; },
      get expanded() { return this.menuOpen ? 'true' : 'false'; },
      get toggleLabel() { return this.menuOpen ? 'Cerrar menú' : 'Abrir menú'; }
    };
  });

  /* Dropdown / accordion group. Desktop: opens on mouse hover (hover-intent delay) and on
   * click / Enter / Space (click pins it open). Mobile accordion reuses toggle/expanded only.
   * Opening one group closes the others through the `nav-dd-open` window event. */
  var seq = 0;
  Alpine.data('navDropdown', function () {
    var timer = null;
    var id = ++seq;
    return {
      open: false,
      pinned: false,
      get expanded() { return this.open ? 'true' : 'false'; },
      announce: function () { this.$dispatch('nav-dd-open', id); },
      cancel: function () { if (timer) { clearTimeout(timer); timer = null; } },
      show: function (e) {
        if (e && e.pointerType && e.pointerType !== 'mouse') return;
        this.cancel();
        if (!this.open) { this.open = true; this.announce(); }
      },
      hide: function (e) {
        if (e && e.pointerType && e.pointerType !== 'mouse') return;
        if (this.pinned) return;
        var self = this;
        this.cancel();
        timer = setTimeout(function () { self.open = false; }, 140);
      },
      toggle: function () {
        this.cancel();
        if (this.open && !this.pinned && this.$root.matches(':hover')) {
          this.pinned = true;           /* opened by hover: the click pins it */
          return;
        }
        this.open = !this.open;
        this.pinned = this.open;
        if (this.open) this.announce();
      },
      openAndFocus: function () {
        var self = this;
        this.cancel();
        this.open = true;
        this.pinned = true;
        this.announce();
        this.$nextTick(function () {
          var a = self.$root.querySelector('a');
          if (a) a.focus();
        });
      },
      close: function () { this.cancel(); this.open = false; this.pinned = false; },
      onEscape: function () {
        if (!this.open) return;
        this.close();
        if (this.$refs.trigger) this.$refs.trigger.focus();
      },
      onFocusOut: function (e) {
        if (e.relatedTarget && !this.$root.contains(e.relatedTarget)) this.close();
      },
      onOther: function (e) { if (e.detail !== id) this.close(); }
    };
  });
});
