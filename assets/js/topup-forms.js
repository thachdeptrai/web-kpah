(function () {
  'use strict';
  var manual = document.getElementById('manual-grant');
  if (manual) {
    manual.addEventListener('submit', function (event) {
      var submit = manual.querySelector('button[type=submit]');
      if (submit && submit.disabled) { event.preventDefault(); return; }
      var character = manual.querySelector('[name=char_id]');
      var currency = manual.querySelector('[name=currency]');
      var mode = manual.querySelector('[name=mode]');
      var amount = manual.querySelector('[name=quantity]');
      var c = character && character.options[character.selectedIndex];
      var m = currency && currency.options[currency.selectedIndex];
      var name = c ? c.text.split(' · ')[0] : '—';
      var msg = 'CỘNG TÀI NGUYÊN CHO NHÂN VẬT\n\n' +
        'Nhân vật: ' + name + '\n' +
        'Tài nguyên: ' + (m ? m.text : '—') + '\n' +
        'Số ' + (mode && mode.value === 'vnd' ? 'VNĐ' : 'tài nguyên') + ': ' + (amount ? amount.value : '—') + '\n\n' +
        'Chỉ tiếp tục khi nhân vật đã THOÁT GAME và bạn đã xác minh chính xác số cộng.';
      if (!window.confirm(msg)) { event.preventDefault(); return; }
      if (submit) { submit.disabled = true; submit.textContent = 'Đang xử lý…'; }
    });
  }
  var card = document.getElementById('card-topup-form');
  var estimate = document.getElementById('tp-estimate');
  if (card && estimate) {
    var amountField = card.querySelector('[name=amount]');
    var currencyField = card.querySelector('[name=currency]');
    var recalc = function () {
      var amount = Number(amountField.value);
      var rate = Number(card.getAttribute('data-rate-' + currencyField.value) || 0);
      if (!Number.isSafeInteger(amount) || amount <= 0 || !Number.isSafeInteger(rate)) {
        estimate.textContent = 'Nhập mệnh giá để ước tính số Lượng nhận.';
        return;
      }
      var discount = Number(card.getAttribute('data-discount-percent'));
      if (!Number.isInteger(discount) || discount < 0 || discount > 99) { estimate.textContent='Chưa cấu hình chiết khấu thẻ.'; return; }
      var net = Math.floor(amount * (100 - discount) / 100);
      var units = Math.floor(net * rate / 10000);
      estimate.textContent = 'Ước tính: ' + units.toLocaleString('vi-VN') + ' ' +
        (currencyField.value === 'luonglock' ? 'Lượng khóa' : 'Lượng thường') +
        ' · Giá trị sau chiết khấu ' + discount + '%: ' + net.toLocaleString('vi-VN') + 'đ.';
    };
    amountField.addEventListener('input', recalc);
    currencyField.addEventListener('change', recalc);
    recalc();
  }
})();
