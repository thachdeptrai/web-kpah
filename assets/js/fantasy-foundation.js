/* NGHIỆN KPAH fantasy foundation — progressive, framework-free. */
(function () {
  'use strict';
  var doc = document;
  var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  function each(selector, callback, parent) {
    Array.prototype.forEach.call((parent || doc).querySelectorAll(selector), callback);
  }
  function closest(target, selector) {
    return target && target.closest ? target.closest(selector) : null;
  }
  function toast(options) {
    options = options || {};
    if (window.KpUI && typeof window.KpUI.toast === 'function') return window.KpUI.toast(options);
    return null;
  }
  function busy(button, enabled, label) {
    if (!button) return;
    if (!button.hasAttribute('data-kpf-label')) button.setAttribute('data-kpf-label', button.textContent);
    button.disabled = !!enabled;
    button.setAttribute('aria-busy', enabled ? 'true' : 'false');
    button.textContent = enabled ? String(label || 'Đang xử lý…') : button.getAttribute('data-kpf-label');
  }
  function initTabs() {
    each('[data-kpf-tabs]', function (list) {
      if (list.dataset.kpfReady === '1') return;
      list.dataset.kpfReady = '1';
      var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"][aria-controls]'));
      if (!tabs.length) return;
      function activate(tab, focus) {
        tabs.forEach(function (candidate) {
          var active = candidate === tab;
          candidate.setAttribute('aria-selected', active ? 'true' : 'false');
          candidate.tabIndex = active ? 0 : -1;
          candidate.classList.toggle('is-active', active);
          var id = candidate.getAttribute('aria-controls');
          var panel = id && doc.getElementById(id);
          if (panel) { panel.hidden = !active; }
        });
        if (focus) tab.focus();
        list.dispatchEvent(new CustomEvent('kpf:tabchange', { bubbles:true, detail:{ tab:tab } }));
      }
      list.addEventListener('click', function (event) {
        var tab = closest(event.target, '[role="tab"][aria-controls]');
        if (tab && list.contains(tab)) activate(tab, false);
      });
      list.addEventListener('keydown', function (event) {
        if (['ArrowLeft','ArrowRight','Home','End'].indexOf(event.key) < 0) return;
        var current = tabs.indexOf(doc.activeElement);
        if (current < 0) return;
        event.preventDefault();
        var next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
          : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        activate(tabs[next], true);
      });
      activate(tabs.filter(function(t){ return t.getAttribute('aria-selected') === 'true'; })[0] || tabs[0], false);
    });
  }
  function initModals() {
    doc.addEventListener('click', function (event) {
      var open = closest(event.target, '[data-kpf-modal-open]');
      if (open) {
        var id = open.getAttribute('data-kpf-modal-open');
        if (!id || id[0] !== '#') return;
        var dialog = doc.getElementById(id.slice(1));
        if (dialog && typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
        return;
      }
      var close = closest(event.target, '[data-kpf-modal-close]');
      if (close) {
        var modal = closest(close, 'dialog');
        if (modal && typeof modal.close === 'function') modal.close();
      }
    });
    each('dialog.kpf-modal[data-kpf-dismiss-backdrop]', function (dialog) {
      dialog.addEventListener('click', function (event) {
        if (event.target === dialog) dialog.close();
      });
    });
  }
  function initNavigation() {
    each('[data-kpf-nav-toggle]', function (toggle) {
      if (toggle.dataset.kpfReady === '1') return;
      toggle.dataset.kpfReady = '1';
      var id = toggle.getAttribute('aria-controls');
      var nav = id && doc.getElementById(id);
      if (!nav) return;
      function set(open) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        nav.classList.toggle('is-open', open);
      }
      toggle.addEventListener('click', function () {
        set(toggle.getAttribute('aria-expanded') !== 'true');
      });
      nav.addEventListener('click', function (event) {
        if (closest(event.target, 'a') && window.matchMedia('(max-width:760px)').matches) set(false);
      });
      doc.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
          set(false);
          toggle.focus();
        }
      });
      window.addEventListener('resize', function () {
        if (window.innerWidth > 760 && toggle.getAttribute('aria-expanded') === 'true') set(false);
      }, {passive:true});
    });
  }
  function initTables() {
    each('.kpf-table--stack', function (table) {
      var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
        return th.textContent.trim();
      });
      each('tbody tr', function (tr) {
        Array.prototype.forEach.call(tr.children, function (cell, index) {
          if (cell.tagName === 'TD' && !cell.hasAttribute('data-label')) {
            cell.setAttribute('data-label', headers[index] || '');
          }
        });
      }, table);
    });
  }
  function initReveals() {
    if (reduceMotion || !('IntersectionObserver' in window)) return;
    var nodes = Array.prototype.slice.call(doc.querySelectorAll('[data-kpf-reveal]'));
    if (!nodes.length) return;
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.remove('kpf-pending');
        observer.unobserve(entry.target);
      });
    }, {threshold:.06,rootMargin:'0px 0px 35px 0px'});
    nodes.forEach(function(node) { node.classList.add('kpf-pending'); observer.observe(node); });
  }
  function init() {
    initTabs();initModals();initNavigation();initTables();initReveals();
    doc.dispatchEvent(new CustomEvent('kpf:ready'));
  }
  window.KpFantasy = Object.freeze({toast:toast, setBusy:busy, init:init});
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init, {once:true});
  else init();
})();
