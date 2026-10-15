/* Admin release upload. Keep native multipart POST available without JavaScript. */
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
  var uploads = form.querySelectorAll('input[type="file"]');
  var uploading = false;
  var mib = 1024 * 1024;

  function limit(name) {
    var attr = form.getAttribute('data-' + name);
    if (!attr) return 0;
    var n = Number(attr);
    return isFinite(n) && n > 0 ? n : 0;
  }
  function formatMb(bytes) {
    return (bytes / mib).toFixed(1).replace(/\.0$/, '') + ' MB';
  }
  function update(percent, title, detail) {
    panel.hidden = false;
    bar.value = percent;
    value.textContent = percent + '%';
    label.textContent = title;
    message.textContent = detail || '';
  }
  function resetWithError(detail) {
    uploading = false;
    if (button) button.disabled = false;
    update(0, 'Không lưu được bản tải game', detail);
  }

  // Selecting a JAR/APK/IPA/ZIP file should not accidentally leave the platform
  // at its default external-URL source; that used to fail on the server.
  for (var i = 0; i < uploads.length; i++) {
    uploads[i].addEventListener('change', function () {
      if (!this.files || !this.files.length) return;
      var match = /^upload\[([a-z]+)\]$/.exec(this.name);
      if (!match) return;
      var source = form.elements['source[' + match[1] + ']'];
      if (source) source.value = 'file';
    });
  }

  function validateUploads() {
    var perFile = limit('upload-max-bytes');
    var postMax = limit('post-max-bytes');
    var appMax = limit('app-max-bytes') || 536870912;
    var totalBytes = 65536; // Multipart overhead and non-file fields.
    var allowed = {
      windows: ['zip', 'exe', 'msi'],
      java: ['jar'],
      android: ['apk'],
      ios: ['ipa']
    };
    for (var i = 0; i < uploads.length; i++) {
      var input = uploads[i];
      if (!input.files || !input.files.length) continue;
      var keyMatch = /^upload\[([a-z]+)\]$/.exec(input.name);
      if (!keyMatch || !allowed[keyMatch[1]]) return 'Nền tảng upload không hợp lệ.';
      var file = input.files[0];
      var extension = file.name.split('.').pop().toLowerCase();
      if (allowed[keyMatch[1]].indexOf(extension) === -1) {
        return 'File ' + keyMatch[1] + ' sai định dạng. Chỉ chấp nhận: ' + allowed[keyMatch[1]].join(', ').toUpperCase() + '.';
      }
      if (file.size < 16) return 'File ' + file.name + ' rỗng hoặc quá nhỏ (dưới 16 byte).';
      if (file.size > appMax) return 'File ' + file.name + ' vượt mức ' + formatMb(appMax) + ' của website. Hãy dùng link HTTPS từ GitHub Releases/CDN.';
      if (perFile && file.size > perFile) {
        return 'File ' + file.name + ' (' + formatMb(file.size) + ') vượt upload_max_filesize (' + formatMb(perFile) + ') trên XAMPP. Hãy chỉnh php.ini hoặc sử dụng link HTTPS.';
      }
      totalBytes += file.size;
      var source = form.elements['source[' + keyMatch[1] + ']'];
      if (!source || source.value !== 'file') {
        return 'Mục ' + keyMatch[1] + ' phải chọn "File upload lên VPS" trước khi lưu.';
      }
    }
    if (postMax && totalBytes > postMax) {
      return 'Tổng dữ liệu upload khoảng ' + formatMb(totalBytes) + ' vượt post_max_size (' + formatMb(postMax) + ') trên XAMPP. Hãy chỉnh php.ini hoặc dùng link HTTPS.';
    }
    return '';
  }

  function readableFailure(xhr, json) {
    if (json && typeof json.message === 'string' && json.message) return json.message;
    var url = xhr.responseURL || '';
    if (/\/quan-tri\/dang-nhap(?:[?#]|$)/.test(url)) {
      return 'Phiên quản trị đã hết hạn. Đăng nhập lại rồi tải file lên.';
    }
    var ray = xhr.getResponseHeader('cf-ray');
    if (xhr.status === 413) {
      return 'File/request quá lớn (HTTP 413). Kiểm tra giới hạn PHP, Apache và Cloudflare. Nếu đi qua Cloudflare, hãy dùng link HTTPS từ GitHub Releases/CDN cho file lớn.';
    }
    if (xhr.status === 403) {
      return ray
        ? 'Cloudflare có thể đã chặn yêu cầu (HTTP 403, Ray ID: ' + ray + '). Kiểm tra Security Events trong Cloudflare.'
        : 'Yêu cầu bị từ chối (HTTP 403). Kiểm tra phiên đăng nhập/CSRF và nhật ký Apache.';
    }
    if (xhr.status === 400 || xhr.status === 422) {
      return ray
        ? 'HTTP ' + xhr.status + ' không trả về JSON. Kiểm tra Cloudflare Security Events theo Ray ID ' + ray + ', hoặc Apache/PHP error.log.'
        : 'Máy chủ trả HTTP ' + xhr.status + ' nhưng không có phản hồi JSON hợp lệ. Kiểm tra tab Network → Response (F12) và Apache/PHP error.log; có thể request bị chặn trước khi tới PHP.';
    }
    if (xhr.status === 429) return 'Thao tác quá nhiều lần. Hãy thử lại sau.';
    if (xhr.status === 500 || xhr.status === 502 || xhr.status === 503 || xhr.status === 504) {
      return 'Máy chủ gặp lỗi HTTP ' + xhr.status + '. Kiểm tra Apache/PHP error.log, quyền ghi storage/game-downloads và tài nguyên VPS.';
    }
    return 'Yêu cầu thất bại (HTTP ' + xhr.status + '). Mở F12 → Network → yêu cầu /quan-tri/tai-game → Response để xem nội dung trả về.';
  }

  form.addEventListener('submit', function (event) {
    if (uploading) { event.preventDefault(); return; }
    event.preventDefault();
    var problem = validateUploads();
    if (problem) { resetWithError(problem); return; }

    uploading = true;
    if (button) button.disabled = true;
    update(0, 'Đang gửi cấu hình và file...', 'Giữ trang mở cho đến khi máy chủ xử lý xong.');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.addEventListener('progress', function (progress) {
      if (progress.lengthComputable && progress.total > 0) {
        var percent = Math.min(100, Math.round(progress.loaded * 100 / progress.total));
        update(percent, percent === 100 ? 'Đã gửi xong, đang xử lý file...' : 'Đang tải file lên VPS...',
          percent === 100 ? 'Máy chủ đang kiểm tra và lưu bản phát hành.' : 'Vui lòng giữ trang này mở.');
      }
    });
    xhr.addEventListener('load', function () {
      var result = null;
      try { result = JSON.parse(xhr.responseText); } catch (e) { /* non-JSON proxy or PHP startup warning */ }
      if (xhr.status >= 200 && xhr.status < 300 && result && result.ok) {
        update(100, 'Đã lưu thành công', 'Đang tải lại cấu hình tải game...');
        window.location.reload();
        return;
      }
      resetWithError(readableFailure(xhr, result));
    });
    xhr.addEventListener('error', function () {
      resetWithError('Mất kết nối tới máy chủ. Kiểm tra đường truyền, Cloudflare và nhật ký Apache.');
    });
    xhr.addEventListener('abort', function () {
      resetWithError('Upload đã bị hủy. Bạn có thể thử lại.');
    });
    xhr.send(new FormData(form));
  });
})();
