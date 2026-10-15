/* Extra confirmation UI; the server independently checks typed target names. */
(function () {
  'use strict';
  var forms = document.querySelectorAll('.gam-mod-form');
  for (var i = 0; i < forms.length; i++) {
    forms[i].addEventListener('submit', function (event) {
      var field = this.querySelector('[data-exact-name]');
      if (!field) { event.preventDefault(); return; }
      var correct = field.getAttribute('data-exact-name') || '';
      if (field.value.trim() !== correct) {
        event.preventDefault();
        field.setCustomValidity('Tên xác nhận không trùng khớp. Nhập đúng: ' + correct);
        field.reportValidity();
        return;
      }
      field.setCustomValidity('');
      var label = this.getAttribute('data-action-label') || 'THAY ĐỔI TRẠNG THÁI';
      var global = this.classList && this.classList.contains('gam-bulk-form');
      var prompt = global
        ? 'XÁC NHẬN MỞ BAN HÀNG LOẠT TOÀN SERVER: ' + label + '?\\nTất cả đối tượng đủ điều kiện sẽ được mở ban. Không thể hoàn tác hàng loạt tự động. Từng đối tượng sẽ được ghi nhật ký.'
        : 'Xác nhận ' + label + ' đối với "' + correct + '"?\\nThao tác chỉ áp dụng cho đối tượng này và sẽ được lưu trong nhật ký.';
      if (!window.confirm(prompt)) {
        event.preventDefault();
      }
    });
    var verify = forms[i].querySelector('[data-exact-name]');
    if (verify) verify.addEventListener('input', function () { this.setCustomValidity(''); });
  }
})();
