(function () {
  'use strict';
  var form = document.getElementById('manual-wallet-credit');
  if (!form) return;
  var busy = false;
  form.addEventListener('submit', function (event) {
    if (busy) { event.preventDefault(); return; }
    if (!form.checkValidity()) return;
    var mode = form.querySelector('[name=mode]');
    var amount = form.querySelector('[name=amount]');
    var username = form.querySelector('[name=username]');
    var text = mode && mode.options[mode.selectedIndex] ? mode.options[mode.selectedIndex].text : '—';
    var value = Number(amount && amount.value);
    if (!Number.isSafeInteger(value) || value < 1 || value > 100000000) {
      event.preventDefault(); return;
    }
    var message = 'XÁC NHẬN CỘNG THỦ CÔNG\n\n'
      + 'Tài khoản: ' + (username ? username.value : '—') + '\n'
      + 'Loại: ' + text + '\n'
      + 'Số tiền: ' + value.toLocaleString('vi-VN') + 'đ\n\n'
      + 'Thao tác không tự hoàn tác, không phải giao dịch nạp thực tế. Tiếp tục?';
    if (!window.confirm(message)) { event.preventDefault(); return; }
    busy = true;
    var button = form.querySelector('button[type=submit]');
    if (button) { button.disabled = true; button.textContent = 'Đang cập nhật…'; }
  });
})();
