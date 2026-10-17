(function () {
  'use strict';
  var form = document.getElementById('recovery-form');
  if (!form) return;
  var feedback = document.getElementById('recovery-feedback');
  var sendButton = document.getElementById('request-reset-code');
  function show(message, good) {
    feedback.textContent = message;
    feedback.className = 'form-feedback ' + (good ? 'is-good' : 'is-bad');
    feedback.setAttribute('role', good ? 'status' : 'alert');
    feedback.setAttribute('aria-live', good ? 'polite' : 'assertive');
    if (window.KpUI && typeof window.KpUI.toast === 'function') window.KpUI.toast({type:good?'success':'error',title:good?'Yêu cầu thành công':'Yêu cầu chưa thành công',message:message});
  }
  function request(data) {
    return fetch(form.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        // The API rotates its CSRF token after each request, including 429.
        if (json.csrf && form.elements.csrf) form.elements.csrf.value = json.csrf;
        return json;
      });
  }
  var sendOriginalHtml = sendButton.innerHTML;
  var resendTimer = null;
  var resendUntil = 0;
  function refreshResendCountdown() {
    var remaining = Math.max(0, Math.ceil((resendUntil - Date.now()) / 1000));
    if (!remaining) {
      window.clearInterval(resendTimer);
      resendTimer = null;
      resendUntil = 0;
      sendButton.innerHTML = sendOriginalHtml;
      sendButton.disabled = false;
      return;
    }
    sendButton.textContent = 'Gửi lại sau ' + remaining + ' giây';
    sendButton.disabled = true;
  }
  function startResendCountdown(seconds) {
    window.clearInterval(resendTimer);
    resendUntil = Date.now() + Math.max(1, Math.min(3600, Number(seconds) || 60)) * 1000;
    refreshResendCountdown();
    resendTimer = window.setInterval(refreshResendCountdown, 1000);
  }
  sendButton.addEventListener('click', function () {
    if (sendButton.disabled || resendUntil > Date.now()) return;
    var identity = document.getElementById('identity').value.trim();
    if (!identity) { show('Nhập tên tài khoản hoặc email trước.', false); return; }
    var data = new FormData(form); data.set('action', 'send_code');
    sendButton.disabled = true; sendButton.textContent = 'Đang gửi…';
    request(data).then(function (result) {
      show(result.message || 'Đã xử lý yêu cầu.', !!result.ok);
      if (result.ok || result.code === 'rate_limit') {
        startResendCountdown(result.retry_after || 60);
      }
    })
      .catch(function () { show('Không kết nối được máy chủ. Hãy thử lại.', false); })
      .finally(function () {
        if (resendUntil <= Date.now()) {
          sendButton.disabled = false;
          sendButton.innerHTML = sendOriginalHtml;
        }
      });
  });
  // Presentation only: reveal/re-hide recovery password fields; never changes API requests.
  Array.prototype.forEach.call(form.querySelectorAll('[data-auth-toggle]'), function (button) {
    button.addEventListener('click', function () {
      var field = document.getElementById(button.getAttribute('data-auth-toggle'));
      if (!field) return;
      var reveal = field.type === 'password';
      field.type = reveal ? 'text' : 'password';
      button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      button.setAttribute('aria-label', reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
      var use = button.querySelector('use');
      if (use) use.setAttribute('href', reveal ? '#i-eye-off' : '#i-eye');
    });
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
