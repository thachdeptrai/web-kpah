/* Progressive enhancement: keep native multipart POST if JS is unavailable. */
(function () {
  'use strict';
  var form = document.getElementById('dl-form');
  var panel = document.getElementById('dl-progress');
  var bar = document.getElementById('dl-progress-bar');
  var label = document.getElementById('dl-progress-label');
  var value = document.getElementById('dl-progress-value');
  var message = document.getElementById('dl-progress-message');
  if (!form || !panel || !bar || !label || !value || !message || !window.FormData || !window.XMLHttpRequest) return;
  var button = form.querySelector('button[type="submit"]');
  var uploading = false;

  function update(percent, title, detail) {
    panel.hidden = false;
    bar.value = percent;
    value.textContent = percent + '%';
    label.textContent = title;
    message.textContent = detail || '';
  }
  form.addEventListener('submit', function (event) {
    if (uploading) { event.preventDefault(); return; }
    event.preventDefault();
    uploading = true;
    if (button) button.disabled = true;
    update(0, 'Đang gửi cấu hình và file...', 'Không đóng tab cho đến khi lưu xong.');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.addEventListener('progress', function (progress) {
      if (progress.lengthComputable && progress.total > 0) {
        var percent = Math.min(100, Math.round(progress.loaded * 100 / progress.total));
        update(percent, percent === 100 ? 'Đã gửi xong, đang xử lý file...' : 'Đang tải file lên VPS...',
          percent === 100 ? 'Máy chủ đang kiểm tra và lưu bản phát hành.' : 'Vui lòng giữ trang này mở.');
      }
    });
    xhr.addEventListener('load', function () {
      var result = null;
      try { result = JSON.parse(xhr.responseText); } catch (e) { /* non-JSON proxy errors */ }
      if (xhr.status >= 200 && xhr.status < 300 && result && result.ok) {
        update(100, 'Đã lưu thành công', 'Đang tải lại dữ liệu...');
        window.location.reload();
        return;
      }
      uploading = false;
      if (button) button.disabled = false;
      update(0, 'Không lưu được',
        result && result.message ? result.message :
          (xhr.status === 413 ? 'File vượt giới hạn upload của proxy hoặc VPS.' :
            'Yêu cầu thất bại (HTTP ' + xhr.status + '). Kiểm tra dung lượng và thử lại.'));
    });
    xhr.addEventListener('error', function () {
      uploading = false;
      if (button) button.disabled = false;
      update(0, 'Mất kết nối', 'Không thể gửi file đến máy chủ. Kiểm tra mạng rồi thử lại.');
    });
    xhr.addEventListener('abort', function () {
      uploading = false;
      if (button) button.disabled = false;
      update(0, 'Đã hủy tải lên', 'Bạn có thể thử lại.');
    });
    xhr.send(new FormData(form));
  });
})();
