/* Client-side bulk picker: purely convenience. PHP validates exact selected IDs. */
(function () {
  'use strict';
  var pickers = document.querySelectorAll('.gam-bulk-form');

  function checked(form) {
    return form.querySelectorAll('[data-bulk-checkbox]:checked').length;
  }
  function refresh(form) {
    var all = form.querySelectorAll('[data-bulk-checkbox]');
    var count = checked(form);
    var selected = [];
    for (var i = 0; i < all.length; i++) {
      if (all[i].checked) selected.push(all[i].value);
    }
    var compact = form.querySelector('[data-selected-csv]');
    if (compact) compact.value = selected.join(',');
    var label = form.querySelector('[data-bulk-count]');
    if (label) label.textContent = 'Đã chọn ' + count + ' / ' + all.length + ' · Giữ ban ' + (all.length - count);
    var button = form.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = count === 0;
      button.setAttribute('aria-disabled', count === 0 ? 'true' : 'false');
    }
  }

  for (var j = 0; j < pickers.length; j++) {
    (function (form) {
      // With JS active, submit IDs via one field rather than >1000 checkbox
      // fields (PHP max_input_vars defaults to 1000). Without JS, checkbox
      // names stay intact as the native form fallback.
      var fields = form.querySelectorAll('[data-bulk-checkbox]');
      var compact = form.querySelector('[data-selected-csv]');
      if (compact) {
        for (var j = 0; j < fields.length; j++) fields[j].removeAttribute('name');
      }
      var filter = form.querySelector('[data-bulk-search]');
      var all = form.querySelectorAll('[data-bulk-item]');
      if (filter) {
        filter.addEventListener('input', function () {
          var query = this.value.trim().toLocaleLowerCase();
          for (var i = 0; i < all.length; i++) {
            var terms = (all[i].getAttribute('data-search') || '').toLocaleLowerCase();
            all[i].hidden = terms.indexOf(query) === -1;
          }
        });
      }
      var buttons = form.querySelectorAll('[data-bulk-select]');
      for (var k = 0; k < buttons.length; k++) {
        buttons[k].addEventListener('click', function () {
          var enable = this.getAttribute('data-bulk-select') === 'all';
          var inputs = form.querySelectorAll('[data-bulk-checkbox]');
          for (var i = 0; i < inputs.length; i++) {
            if (!enable || !inputs[i].closest('[data-bulk-item]').hidden) {
              inputs[i].checked = enable;
            }
          }
          refresh(form);
        });
      }
      form.addEventListener('change', function (event) {
        if (event.target && event.target.hasAttribute('data-bulk-checkbox')) refresh(form);
      });
      refresh(form);
    })(pickers[j]);
  }

  var forms = document.querySelectorAll('.gam-mod-form');
  for (var n = 0; n < forms.length; n++) {
    forms[n].addEventListener('submit', function (event) {
      var target = this.querySelector('[data-exact-name]');
      if (!target) { event.preventDefault(); return; }
      var correct = target.getAttribute('data-exact-name') || '';
      if (target.value.trim() !== correct) {
        event.preventDefault();
        target.setCustomValidity('Tên xác nhận không trùng khớp. Nhập đúng: ' + correct);
        target.reportValidity();
        return;
      }
      target.setCustomValidity('');
      var isBulk = this.classList && this.classList.contains('gam-bulk-form');
      var label = this.getAttribute('data-action-label') || 'THAY ĐỔI TRẠNG THÁI';
      var prompt;
      if (isBulk) {
        var count = checked(this);
        if (count < 1) {
          event.preventDefault();
          return;
        }
        prompt = 'XÁC NHẬN ' + label + '?\n'
          + 'Mở ban đúng ' + count + ' đối tượng đã tích.\n'
          + 'Những đối tượng không tích sẽ tiếp tục bị ban.\n'
          + 'Thao tác có ghi nhật ký từng đối tượng.';
      } else {
        prompt = 'Xác nhận ' + label + ' đối với "' + correct + '"?\n'
          + 'Chỉ thay đổi đối tượng này và ghi nhật ký.';
      }
      if (!window.confirm(prompt)) event.preventDefault();
    });
    var verify = forms[n].querySelector('[data-exact-name]');
    if (verify) verify.addEventListener('input', function () {
      this.setCustomValidity('');
    });
  }
})();
