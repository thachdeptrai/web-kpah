/**
 * NGHIEN KPAH UI Foundation
 * Progressive, dependency-free helpers for theme, toast, modal and tabs.
 */
(function () {
  'use strict';

  var doc = document;
  var root = doc.documentElement;
  root.setAttribute('data-ui-theme', 'kpah');
  root.classList.add('kp-ui-ready');

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function one(selector, context) {
    return (context || doc).querySelector(selector);
  }

  function all(selector, context) {
    return Array.prototype.slice.call((context || doc).querySelectorAll(selector));
  }

  function safeText(value) {
    return value == null ? '' : String(value);
  }

  function createBackdrop() {
    if (!doc.body || !doc.body.hasAttribute('data-ui-backdrop') || one('.kp-ui-backdrop')) return;
    var backdrop = doc.createElement('div');
    backdrop.className = 'kp-ui-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.innerHTML = '<span class="kp-ui-clouds"></span>';
    doc.body.prepend(backdrop);
  }

  function toastRegion() {
    var region = one('.kp-ui-toast-region');
    if (region) return region;
    region = doc.createElement('div');
    region.className = 'kp-ui-toast-region';
    region.setAttribute('role', 'region');
    region.setAttribute('aria-label', 'Thông báo');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-relevant', 'additions');
    region.setAttribute('aria-atomic', 'false');
    doc.body.appendChild(region);
    return region;
  }

  function toastIcon(type) {
    if (type === 'success') return '✓';
    if (type === 'error') return '×';
    if (type === 'warning') return '!';
    return 'i';
  }

  function removeToast(node) {
    if (!node || node.dataset.removing === '1') return;
    node.dataset.removing = '1';
    node.classList.add('is-leaving');
    window.setTimeout(function () {
      if (node.parentNode) node.parentNode.removeChild(node);
      var region = one('.kp-ui-toast-region');
      if (region && !region.children.length) region.remove();
    }, reduceMotion ? 20 : 240);
  }

  function showToast(options) {
    options = options || {};
    var type = ['success', 'error', 'warning', 'info'].indexOf(options.type) >= 0 ? options.type : 'info';
    var duration = Math.max(1800, Math.min(Number(options.duration) || 4800, 12000));
    var messageText = safeText(options.message);
    var node = doc.createElement('article');
    node.className = 'kp-ui-toast kp-ui-toast--' + type;
    node.setAttribute('role', type === 'error' ? 'alert' : 'status');

    var icon = doc.createElement('span');
    icon.className = 'kp-ui-toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = toastIcon(type);

    var copy = doc.createElement('div');
    copy.className = 'kp-ui-toast-copy';
    var title = doc.createElement('strong');
    title.textContent = safeText(options.title || (type === 'success' ? 'Thành công' : type === 'error' ? 'Thất bại' : type === 'warning' ? 'Lưu ý' : 'Thông báo'));
    var message = doc.createElement('p');
    message.textContent = messageText;
    copy.appendChild(title);
    if (messageText) copy.appendChild(message);

    var close = doc.createElement('button');
    close.type = 'button';
    close.className = 'kp-ui-toast-close';
    close.setAttribute('aria-label', 'Đóng thông báo');
    close.textContent = '×';
    close.addEventListener('click', function () { removeToast(node); });

    node.appendChild(icon);
    node.appendChild(copy);
    node.appendChild(close);

    // Limit visible stack size on smaller screens. Oldest messages exit first.
    var region = toastRegion();
    while (region.children.length >= 5) removeToast(region.firstElementChild);
    region.appendChild(node);

    var timer = window.setTimeout(function () { removeToast(node); }, duration);
    node.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
    node.addEventListener('mouseleave', function () { timer = window.setTimeout(function () { removeToast(node); }, duration); });
    return node;
  }

  function toastCompat(type, title, message, duration) {
    if (type && typeof type === 'object') return showToast(type);
    return showToast({type:type,title:title,message:message,duration:duration});
  }

  function openModal(target) {
    var modal = typeof target === 'string' ? one(target) : target;
    if (!modal) return false;
    if (typeof modal.showModal === 'function') modal.showModal();
    else {
      modal.removeAttribute('hidden');
      modal.setAttribute('open', '');
      modal.setAttribute('role', 'dialog');
      doc.body.style.overflow = 'hidden';
    }
    return true;
  }

  function closeModal(target) {
    var modal = typeof target === 'string' ? one(target) : target;
    if (!modal) return false;
    if (typeof modal.close === 'function' && modal.open) modal.close();
    else {
      modal.setAttribute('hidden', '');
      modal.removeAttribute('open');
      doc.body.style.overflow = '';
    }
    return true;
  }

  function initModals() {
    doc.addEventListener('click', function (event) {
      var opener = event.target.closest('[data-ui-modal-open]');
      if (opener) {
        event.preventDefault();
        openModal(opener.getAttribute('data-ui-modal-open'));
        return;
      }
      var closer = event.target.closest('[data-ui-modal-close]');
      if (closer) {
        event.preventDefault();
        closeModal(closer.closest('dialog,[role="dialog"]'));
      }
    });

    all('dialog.kp-ui-modal').forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal && modal.hasAttribute('data-ui-close-backdrop')) closeModal(modal);
      });
      modal.addEventListener('cancel', function () { doc.body.style.overflow = ''; });
      modal.addEventListener('close', function () { doc.body.style.overflow = ''; });
    });
  }

  function activateTab(tab, focus) {
    var list = tab.closest('[data-ui-tabs]');
    if (!list) return;
    var tabs = all('[role="tab"]', list);
    var selector = tab.getAttribute('aria-controls');
    tabs.forEach(function (item) {
      var active = item === tab;
      item.setAttribute('aria-selected', active ? 'true' : 'false');
      item.setAttribute('tabindex', active ? '0' : '-1');
      item.classList.toggle('is-active', active);
      var panelId = item.getAttribute('aria-controls');
      if (panelId) {
        var panel = doc.getElementById(panelId);
        if (panel) panel.hidden = !active;
      }
    });
    if (focus) tab.focus();
    list.dispatchEvent(new CustomEvent('kpui:tabchange', { bubbles: true, detail: { tab: tab, panel: selector } }));
  }

  function initTabs() {
    all('[data-ui-tabs]').forEach(function (list) {
      var tabs = all('[role="tab"]', list);
      if (!tabs.length) return;
      var selected = tabs.filter(function (tab) { return tab.getAttribute('aria-selected') === 'true'; })[0] || tabs[0];
      activateTab(selected, false);
      list.addEventListener('click', function (event) {
        var tab = event.target.closest('[role="tab"]');
        if (!tab || !list.contains(tab)) return;
        event.preventDefault();
        activateTab(tab, false);
      });
      list.addEventListener('keydown', function (event) {
        var current = event.target.closest('[role="tab"]');
        if (!current) return;
        var index = tabs.indexOf(current);
        var next = null;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = tabs[(index + 1) % tabs.length];
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = tabs[(index - 1 + tabs.length) % tabs.length];
        if (event.key === 'Home') next = tabs[0];
        if (event.key === 'End') next = tabs[tabs.length - 1];
        if (next) {
          event.preventDefault();
          activateTab(next, true);
        }
      });
    });
  }

  function initStackTables() {
    all('.kp-ui-table--stack').forEach(function (table) {
      var labels = all('thead th', table).map(function (th) { return th.textContent.trim(); });
      all('tbody tr', table).forEach(function (row) {
        all('td', row).forEach(function (cell, index) {
          if (!cell.hasAttribute('data-label') && labels[index]) cell.setAttribute('data-label', labels[index]);
        });
      });
    });
  }

  function initDisclosure() {
    doc.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-ui-toggle]');
      if (!trigger) return;
      var target = one(trigger.getAttribute('data-ui-toggle'));
      if (!target) return;
      event.preventDefault();
      var expanded = trigger.getAttribute('aria-expanded') === 'true';
      trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      target.hidden = expanded;
    });
  }

  function consumeDeclarativeToasts() {
    all('[data-ui-toast]').forEach(function (source) {
      showToast({
        type: source.getAttribute('data-ui-toast') || 'info',
        title: source.getAttribute('data-ui-toast-title') || '',
        message: source.getAttribute('data-ui-toast-message') || source.textContent.trim(),
        duration: source.getAttribute('data-ui-toast-duration')
      });
      source.remove();
    });
    var params = new URLSearchParams(window.location.search);
    if (params.get('expired') === '1') {
      showToast({type:'error',title:'Phiên đã hết hạn',message:'Vui lòng đăng nhập lại để tiếp tục.'});
    }
  }

  function boot() {
    createBackdrop();
    initModals();
    initTabs();
    initStackTables();
    initDisclosure();
    consumeDeclarativeToasts();
    doc.dispatchEvent(new CustomEvent('kpui:ready'));
  }

  window.KpUI = Object.freeze({
    toast: showToast,
    openModal: openModal,
    closeModal: closeModal,
    activateTab: activateTab
  });
  // Compatibility for older AJAX forms; new code should use KpUI.toast({...}).
  window.kpToast = toastCompat;
  window.KpFantasy = Object.freeze({toast:function (options) { return showToast(options); }});

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
}());
