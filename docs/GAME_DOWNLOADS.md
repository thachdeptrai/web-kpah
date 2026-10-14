# Quản lý phát hành game KPAH

## Sử dụng

1. Đăng nhập bằng tài khoản **admin** tại `/quan-tri/dang-nhap`.
2. Vào **Tải game** → `/quan-tri/tai-game`.
3. Với mỗi nền tảng **Windows/PC**, **Android** và **iOS**:
   - Chọn nguồn **Link tải bên ngoài** hoặc **File upload lên VPS**.
   - Nếu dùng link: dán URL HTTPS từ GitHub Releases/CDN (hoặc URL nội bộ bắt đầu bằng `/`).
   - Nếu upload: chọn tệp `.zip/.exe/.msi` (PC), `.apk` (Android), `.ipa` (iOS). Không chọn tệp mới nếu muốn giữ bản cũ.
   - Tích **Cho phép người chơi tải** khi bản phát hành sẵn sàng.
4. Bấm **Lưu cấu hình tải game**, sau đó mở `/#tai-game` kiểm tra nút tải.
5. Muốn đóng phát hành, **bỏ tích** rồi lưu. Đường dẫn tải file qua website sẽ trả HTTP 404 ngay khi đã tắt.

Role `key` không được phép mở trang quản lý tải game.

## Nơi lưu dữ liệu

- Metadata: `storage/game-downloads/catalog.json`.
- File nhị phân: `storage/game-downloads/<tên-ngẫu-nhiên>.<phần-mở-rộng>`.
- Đường dẫn tải qua PHP: `/tai-game/tep?nen-tang=windows`, `?nen-tang=android`, `?nen-tang=ios`.
- File nằm ngoài vùng asset công khai, bị chặn trực tiếp bởi `.htaccess` ở gốc và `storage/.htaccess`. Trình duyệt nhận `Content-Disposition: attachment` và `Content-Type: application/octet-stream`.
- Download hỗ trợ `HEAD`, `GET` và một byte-range `Range` để tải tiếp. Không cần đăng nhập game để tải.
- Khi admin đổi file, bản cũ không còn được tham chiếu sẽ được thử xóa để tiết kiệm dung lượng. Nếu file đang bị Windows khóa bởi lượt tải dở, xóa có thể không thành công; quản trị viên có thể dọn file cũ sau.
- `storage/` nằm trong `.gitignore`; `git pull` không xóa metadata và file đã upload.

Nếu chưa từng lưu trong Admin, trang chủ vẫn đọc cấu hình `links.download_android`, `links.download_windows`, `links.download_ios` cũ (nếu có). Sau khi Admin lưu lần đầu, catalog sẽ là nguồn dữ liệu chính và checkbox sẽ kiểm soát trạng thái.

## XAMPP / Windows Server

Với file cỡ 512 MB, có thể cấu hình trong `C:\xampp\php\php.ini`:

```ini
file_uploads = On
upload_max_filesize = 512M
post_max_size = 550M
max_input_time = 600
max_execution_time = 600
```

Khởi động lại Apache và kiểm tra quyền ghi của tài khoản chạy Apache đối với `C:\xampp\htdocs\storage\`. Đảm bảo ổ đĩa có đủ dung lượng. Nếu request quá lớn, Cloudflare hoặc proxy trước Apache có thể từ chối upload ngay cả khi PHP đã tăng giới hạn; với các file lớn nên dùng GitHub Releases/CDN rồi dán link.

**Khuyến nghị:** chọn GitHub Releases/CDN làm nguồn chính cho game cỡ lớn hoặc có nhiều lượt tải. Upload trực tiếp VPS phù hợp khi file nhỏ, cần quản lý riêng hoặc có ít người tải. Tải qua PHP có thể chiếm worker Apache/PHP và băng thông VPS trong suốt quá trình truyền.

**iOS:** IPA cần được ký và phân phối theo cách tương thích với thiết bị; một link IPA chỉ tải tệp, không tự cài ứng dụng trên iPhone.

## Kiểm tra và an toàn

- Upload yêu cầu phiên admin hợp lệ, CSRF, giới hạn thao tác và danh sách phần mở rộng cho từng nền tảng.
- File upload được kiểm tra kích thước tối đa 512 MB, chữ ký ZIP/PE/OLE, và cấu trúc APK/IPA nếu PHP có ZipArchive.
- Không chấp nhận URL `javascript:`, `data:`, `http:`, `//host` hoặc URL có tài khoản/mật khẩu.
- Catalog được ghi qua file tạm, khóa `flock`, không chứa thông tin mật.
- Chạy `php tests/downloads_smoke.php` để kiểm tra logic checkbox, URL và chống traversal (test sử dụng thư mục tạm riêng).
- Kiểm tra trực tiếp trên VPS sau khi pull: trang admin, tải thử APK/ZIP/IPA, đóng checkbox và xác nhận URL tải file trả 404. Kiểm tra trên cả điện thoại và máy tính.

Không upload source PHP hoặc SQL qua chức năng phát hành game. File phát hành nên được kiểm tra và build từ nguồn tin cậy.
