# Bảo mật NGHIỆN KPAH

Tài liệu vận hành cho website PHP/MariaDB hiện tại. Việc thêm quy tắc web hoặc bộ lọc trong PHP **không thể bảo đảm website tuyệt đối an toàn**; cần kết hợp quyền MySQL, cấu hình VPS, bản vá phần mềm và giám sát.

## Những lớp bảo vệ đã có trong mã nguồn

- **Admin:** xác thực mật khẩu hash, truy vấn PDO có tham số, kiểm tra quyền `role=admin` và `is_active=1` từ DB, session timeout/rotation, kiểm tra user-agent, giới hạn đăng nhập theo IP + tài khoản, hỗ trợ TOTP 2FA, chống dùng lại mã TOTP, tùy chọn IP allowlist. Các trang nạp admin gọi cùng `kp_admin_require()`.
- **Phiên & biểu mẫu:** cookie HttpOnly/SameSite, Secure nếu PHP nhận biết HTTPS; CSRF với hành động qua form, CSP chống script inline, chống iframe, không in lỗi SQL ra trình duyệt, validate và escape dữ liệu.
- **Database:** dùng PDO prepared statements cho đầu vào biến thiên; những chỗ dùng tên cột động (chẳng hạn Xu/Lượng/Lượng khóa) chỉ lựa chọn từ danh sách cố định ở PHP.
- **Chống lộ file:** Apache `.htaccess` chặn `storage/`, `includes/`, `database/`, `tests/`, `docs/`, dotfiles, file SQL/log/dump và những bản backup có tên nhận diện được. File nén tùy ý vẫn phải đưa ra ngoài web root. `.gitignore` loại trừ secrets và dữ liệu vận hành.
- **Nạp thẻ:** vẫn khóa gửi thẻ thật tới khi xác minh đối tác và tích hợp game server. Không mở bán chỉ vì giao diện đã có.

## Bắt buộc kiểm tra ở VPS

### 1. Chặn quyền DB ở mức tài khoản MySQL

Không cho website đăng nhập MySQL bằng `root` hoặc tài khoản có `FILE`, `SUPER`, `GRANT OPTION`, `CREATE USER`, `DROP`, `ALTER` nếu không thật sự cần. Phân quyền **chỉ tới những bảng và loại thao tác mà các chức năng đang dùng**. Vì web vừa có đăng ký, mã giới thiệu và thao tác cộng tài nguyên, không thể thu về chỉ quyền `SELECT` mà giữ nguyên chức năng.

Không đặt thư mục backup SQL trong Apache DocumentRoot. Nếu bản dump hoặc mật khẩu từng bị công khai, cần **xóa công khai và thay đổi bí mật tương ứng**; thêm `.gitignore` không xóa được dữ liệu đã commit trước đây.

### 2. Bật 2FA TOTP cho admin

Admin đã có hỗ trợ các biến môi trường:

```text
KPAH_ADMIN_REQUIRE_TOTP=1
KPAH_ADMIN_TOTP_SECRET=<BASE32_SECRET_LUU_RIENG_TREN_VPS>
```

Nếu có nhiều admin, thay biến đơn bằng `KPAH_ADMIN_TOTP_SECRETS` chứa JSON ánh xạ username → secret Base32. **Đăng ký khóa vào ứng dụng Authenticator trước khi bật bắt buộc**, tránh tự khóa tài khoản. Secret phải ở môi trường máy chủ, không commit và không in ra trang web.

Tài khoản admin dùng mật khẩu dài/riêng biệt; nếu nghi bị lộ, thay mật khẩu và secret 2FA. Tùy chọn `KPAH_ADMIN_ALLOWED_IPS` nhận danh sách IP tĩnh phân tách bằng dấu phẩy. Cẩn thận nếu IP nhà mạng thay đổi. Sau proxy, chỉ bật `security.trusted_ip_header` khi đã giới hạn truy cập origin chỉ qua proxy tin cậy.

### 3. HTTPS, Cloudflare và Apache

- Chạy HTTPS trên site và origin; với Cloudflare dùng TLS **Full (strict)** và chứng chỉ origin hợp lệ.
- Kiểm tra `mod_rewrite`, `AllowOverride` và đường dẫn DocumentRoot: nếu Apache bỏ qua `.htaccess`, các quy tắc chặn file **không còn hiệu lực**.
- Nếu chuyển sang Nginx/IIS, tự cấu hình deny tương đương: Nginx/IIS không chạy `.htaccess`.
- Có thể bật WAF/rate limit tại Cloudflare cho `/admin/*`, `/topup/admin/*`, `/api/*`, `/topup/card-callback.php` (callback cần cho phép IP/dấu ký đối tác thực tế); ưu tiên Managed Challenge cho admin thay vì chặn nhầm callback.
- Không để FTP, Remote Desktop, MySQL 3306 hoặc giao diện quản trị DB mở công khai nếu không có VPN/IP allowlist.
- Lưu backup ra vị trí nằm ngoài web root, đặt quyền truy cập hệ thống file và mã hóa backup cần thiết.

### 4. Kiểm tra bằng URL sau khi pull

Đăng nhập trình duyệt và kiểm tra các đường dẫn sau **không được trả về nội dung file** (403 hoặc 404 phù hợp):

```text
https://nghienkpah.io.vn/database/referral_codes.sql
https://nghienkpah.io.vn/includes/db.php
https://nghienkpah.io.vn/storage/topup/rates.json
https://nghienkpah.io.vn/.git/config
https://nghienkpah.io.vn/backup.sql
https://nghienkpah.io.vn/backup.zip
https://nghienkpah.io.vn/check.php
```

Các địa chỉ nhạy cảm nếu không tồn tại phải là 404/403; tuyệt đối không cố tạo file nhạy cảm để thử trên server public. Kiểm tra chế độ không đăng nhập tại `/admin/index.php`, `/admin/referrals.php`, `/topup/admin/grant.php` phải bị chuyển về đăng nhập hoặc chặn.

### 5. Kiểm thử mã nguồn

```bat
C:\xampp\php\php.exe tests\security_smoke.php
C:\xampp\php\php.exe tests\topup_smoke.php
```

Workflow `.github/workflows/topup-lint.yml` chạy PHP lint và kiểm tra bảo mật offline. Những bài test này không thay thế **pentest được ủy quyền**, quét phụ thuộc PHP/JS và kiểm thử trên môi trường staging.

## Những giới hạn cần nhớ

- Không thể tuyên bố "không thể SQL injection" chỉ nhờ `.htaccess`. Cần tiếp tục audit query động và tất cả API khi thay code.
- SQL injection, quyền MySQL quá rộng, thông tin xác thực bị lộ, mật khẩu yếu và VPS bị chiếm quyền là những đường khác nhau dẫn tới lộ/dump dữ liệu.
- Chức năng cộng tài nguyên đang đụng bảng MyISAM nên cần đối soát game server và nhật ký; bảo vệ admin chưa làm giao dịch thành atomic.
- Quy tắc `.htaccess` chỉ chặn các file nén có tên gợi ý backup/dump/database hoặc dạng SQL nén, không chặn mọi file ZIP. Không lưu backup nén dưới web root. Nếu cung cấp bản cài game dạng ZIP, nên phát qua GitHub Releases/CDN riêng ngoài web root hoặc thiết kế route download đã kiểm soát.
- Không đặt tài liệu kỹ thuật hay dữ liệu chứa secrets công khai qua HTTP.
