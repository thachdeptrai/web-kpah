/**
 * Progressive interactions for the public landing page.
 * No API calls or business logic live here.
 */
(function () {
  'use strict';

  var doc = document;
  var header = doc.querySelector('[data-landing-header]');
  var menuButton = doc.querySelector('[data-landing-menu]');
  var menu = doc.getElementById('lp-menu');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function setHeaderState() {
    if (header) header.classList.toggle('is-scrolled', window.scrollY > 18);
  }

  function closeMenu() {
    if (!menuButton || !menu) return;
    menuButton.setAttribute('aria-expanded', 'false');
    menu.classList.remove('is-open');
  }

  if (header) {
    setHeaderState();
    window.addEventListener('scroll', setHeaderState, { passive: true });
  }

  if (menuButton && menu) {
    menuButton.addEventListener('click', function () {
      var open = menuButton.getAttribute('aria-expanded') === 'true';
      menuButton.setAttribute('aria-expanded', open ? 'false' : 'true');
      menu.classList.toggle('is-open', !open);
    });
    menu.addEventListener('click', function (event) {
      if (event.target.closest('a')) closeMenu();
    });
    doc.addEventListener('pointerdown', function (event) {
      if (!menu.classList.contains('is-open')) return;
      if (menu.contains(event.target) || menuButton.contains(event.target)) return;
      closeMenu();
    });
    doc.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMenu();
    });
  }

  var revealNodes = Array.prototype.slice.call(doc.querySelectorAll('[data-landing-reveal]'));
  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealNodes.forEach(function (node) { node.classList.add('is-visible'); });
  } else {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -7% 0px', threshold: 0.08 });
    revealNodes.forEach(function (node) { observer.observe(node); });
  }
}());
