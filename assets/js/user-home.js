(function () {
  'use strict';
  var form = document.getElementById('profile-form');
  if (!form) return;
  var feedback = document.getElementById('profile-feedback');
  var sendButton = document.getElementById('send-code');
  var emailInput = document.getElementById('email');
  var codeInput = document.getElementById('email_code');
  var originalEmail = emailInput ? emailInput.value.trim().toLowerCase() : '';
  function show(message, good) {
    feedback.textContent = message;
    feedback.className = 'form-feedback ' + (good ? 'is-good' : 'is-bad');
  }
  function send(data) {
    return fetch(form.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok && !j.message) j.message = 'Không xử lý được yêu cầu.'; return j; }); });
  }
  sendButton.addEventListener('click', function () {
    var email = emailInput.value.trim().toLowerCase();
    if (!email || !emailInput.checkValidity()) { show('Hãy nhập email hợp lệ trước khi yêu cầu mã.', false); emailInput.focus(); return; }
    if (email === originalEmail) { show('Email này đang được liên kết với tài khoản.', false); return; }
    sendButton.disabled = true;
    sendButton.textContent = 'Đang gửi…';
    var data = new FormData(form); data.set('action', 'send_email_code');
    send(data).then(function (result) { show(result.message || 'Đã xử lý yêu cầu.', !!result.ok); })
      .catch(function () { show('Không kết nối được máy chủ. Hãy thử lại.', false); })
      .finally(function () { sendButton.disabled = false; sendButton.textContent = 'Gửi mã'; });
  });
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var data = new FormData(form); data.set('action', 'update');
    if (emailInput.value.trim().toLowerCase() !== originalEmail && !/^\d{6}$/.test(codeInput.value.trim())) {
      show('Đổi email cần mã xác minh 6 chữ số gửi tới email mới.', false); return;
    }
    send(formDataWithEndpoint(data)).then(function (result) {
      show(result.message || 'Đã xử lý yêu cầu.', !!result.ok);
      if (result.ok) {
        originalEmail = (result.email || '').toLowerCase();
        emailInput.value = result.email || emailInput.value;
        document.getElementById('phone').value = result.phone || '';
        codeInput.value = '';
        window.setTimeout(function () { window.location.reload(); }, 1000);
      }
    }).catch(function () { show('Không kết nối được máy chủ. Hãy thử lại.', false); });
  });
  function formDataWithEndpoint(data) { return data; }

  var passwordForm = document.getElementById('change-password-form');
  if (passwordForm) {
    var passwordFeedback = document.getElementById('password-feedback');
    var passwordSubmit = document.getElementById('change-password-submit');
    passwordForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var currentPassword = document.getElementById('current_password');
      var newPassword = document.getElementById('new_password');
      var confirmPassword = document.getElementById('confirm_password');
      function passwordMessage(message, good) {
        passwordFeedback.textContent = message;
        passwordFeedback.className = 'form-feedback ' + (good ? 'is-good' : 'is-bad');
      }
      if (newPassword.value !== confirmPassword.value) {
        passwordMessage('Mật khẩu mới và phần xác nhận không trùng nhau.', false);
        confirmPassword.focus();
        return;
      }
      if (currentPassword.value === newPassword.value) {
        passwordMessage('Mật khẩu mới phải khác mật khẩu hiện tại.', false);
        newPassword.focus();
        return;
      }
      passwordSubmit.disabled = true;
      var label = passwordSubmit.querySelector('span:first-child');
      if (label) label.textContent = 'Đang cập nhật…';
      fetch(passwordForm.dataset.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        body: new FormData(passwordForm),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (response) {
        return response.json().then(function (result) {
          if (!response.ok && !result.message) result.message = 'Không xử lý được yêu cầu.';
          return result;
        });
      }).then(function (result) {
        passwordMessage(result.message || 'Đã xử lý yêu cầu.', !!result.ok);
        if (result.ok) passwordForm.reset();
      }).catch(function () {
        passwordMessage('Không kết nối được máy chủ. Hãy thử lại.', false);
      }).finally(function () {
        passwordSubmit.disabled = false;
        if (label) label.textContent = 'Cập nhật mật khẩu';
      });
    });
  }

})();