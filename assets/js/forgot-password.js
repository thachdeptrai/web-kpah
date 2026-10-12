(function () {
  'use strict';
  var form = document.getElementById('recovery-form');
  if (!form) return;
  var feedback = document.getElementById('recovery-feedback');
  var sendButton = document.getElementById('request-reset-code');
  function show(message, good) { feedback.textContent = message; feedback.className = 'form-feedback ' + (good ? 'is-good' : 'is-bad'); }
  function request(data) {
    return fetch(form.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); });
  }
  sendButton.addEventListener('click', function () {
    var identity = document.getElementById('identity').value.trim();
    if (!identity) { show('Nhập tên tài khoản hoặc email trước.', false); return; }
    var data = new FormData(form); data.set('action', 'send_code');
    sendButton.disabled = true; sendButton.textContent = 'Đang gửi…';
    request(data).then(function (result) { show(result.message || 'Đã xử lý yêu cầu.', !!result.ok); })
      .catch(function () { show('Không kết nối được máy chủ. Hãy thử lại.', false); })
      .finally(function () { sendButton.disabled = false; sendButton.innerHTML = 'Gửi mã về Gmail <span>↗</span>'; });
  });
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var password = document.getElementById('new_password').value;
    var confirm = document.getElementById('confirm_password').value;
    if (password !== confirm) { show('Hai mật khẩu không trùng nhau.', false); return; }
    var data = new FormData(form); data.set('action', 'reset');
    request(data).then(function (result) {
      show(result.message || 'Đã xử lý yêu cầu.', !!result.ok);
      if (result.ok && result.redirect) window.setTimeout(function () { window.location.href = result.redirect; }, 1200);
    }).catch(function () { show('Không kết nối được máy chủ. Hãy thử lại.', false); });
  });
})();