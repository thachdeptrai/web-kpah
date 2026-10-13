(function () {
  'use strict';
  var input = document.getElementById('password');
  var button = document.getElementById('toggle-password');
  if (!input || !button) return;
  button.addEventListener('click', function () {
    var reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
    button.setAttribute('aria-label', reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
    var use = button.querySelector('use');
    if (use) use.setAttribute('href', reveal ? '#i-eye-off' : '#i-eye');
  });
  var form = document.getElementById('login-form');
  var submit = document.getElementById('login-submit');
  if (form && submit) {
    form.addEventListener('submit', function () {
      if (!form.checkValidity()) return;
      submit.disabled = true;
      submit.classList.add('is-loading');
      var label = submit.querySelector('span:nth-of-type(1)');
      if (label) label.textContent = 'Đang đăng nhập…';
    });
  }
})();