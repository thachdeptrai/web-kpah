/* Admin nav progressive enhancement: native <details> works without JS. */
(function () {
  'use strict';
  var nav = document.querySelector('.kp-admin-nav-v2');
  if (!nav) return;
  var menus = Array.prototype.slice.call(nav.querySelectorAll('details.kp-admin-nav-dropdown, details.kp-admin-nav-mobile'));
  menus.forEach(function (menu) {
    menu.addEventListener('toggle', function () {
      if (!menu.open) return;
      menus.forEach(function (other) {
        if (other !== menu) other.open = false;
      });
    });
  });
  document.addEventListener('pointerdown', function (event) {
    if (nav.contains(event.target)) return;
    menus.forEach(function (menu) { menu.open = false; });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var open = menus.filter(function (menu) { return menu.open; });
    if (!open.length) return;
    open.forEach(function (menu) { menu.open = false; });
    var trigger = open[open.length - 1].querySelector('summary');
    if (trigger) trigger.focus();
  });
  window.addEventListener('resize', function () {
    menus.forEach(function (menu) { menu.open = false; });
  });
})();
