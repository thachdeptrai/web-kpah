# KPAH – Module đăng ký tài khoản

Trang đăng ký tài khoản cho game KPAH (PHP + MySQL/MariaDB), giao diện MMORPG fantasy tối màu, responsive,
ghi tài khoản **trực tiếp vào bảng `team_user`** đúng định dạng game đang dùng.

> **Đọc trước – giới hạn của bản bàn giao này**
> Mình chỉ nhận được `kpah.sql`. Mình **không có** source hiện tại (`config.php`, `index.php`, `admin/`, `api/`, `ip/`, `.htaccess`, trang đăng nhập).
> Vì vậy module được làm **độc lập, không ghi đè** file nào của bạn, và mọi thứ về database đều lấy từ `kpah.sql`.
> Chi tiết ở mục *Chưa xác minh được* bên dưới.

---

## 1. Schema đã xác minh từ `kpah.sql`

| Nội dung | Kết quả | Hệ quả cho code |
|---|---|---|
| Bảng tài khoản | `team_user` (MyISAM, utf8) | Chỉ `INSERT` vào bảng này, không tạo bảng/cột mới |
| Mật khẩu | Dạng `PASSWORD()` của MySQL: `*` + SHA1(SHA1(mk)) viết hoa, 41 ký tự | PHP: `'*'.strtoupper(sha1(sha1($pw,true)))`. Đã đối chiếu: `123456` → `*6BB4837E…2AD9` **trùng dữ liệu thật** |
| `username` | `varchar(20)`, UNIQUE `uk_team_user_username` | Kiểm tra trùng ở PHP **và** có ràng buộc DB; bắt lỗi 1062 |
| `userid` | `INT UNSIGNED NOT NULL`, UNIQUE `Index_2`, **không tự tăng, không default** | Web tự cấp `MAX(userid)+1` trong `GET_LOCK`, thử lại nếu trùng |
| `id` | khoá chính AUTO_INCREMENT | để DB tự cấp |
| `phone` | `NOT NULL DEFAULT ''`, dữ liệu thật dạng `+84xxxxxxxxx` | Tuỳ chọn nhập; chuẩn hoá về `+84…` |
| `newagent`/`newprovider`/`ban`/`fromgame` | có default | `newagent`/`newprovider` để DB áp default `'-1'`; `ban=0`, `fromgame=0`, `provider=0`, `agent=-1` |
| `regdate` | `datetime`, dữ liệu thật đang theo giờ Việt Nam | PHP ghi giờ `Asia/Ho_Chi_Minh` (đổi trong config) |

**Không cần migration.** Các ràng buộc cần thiết đã có sẵn. Chạy `database/verify_schema.sql` (chỉ đọc) trên DB thật để đối chiếu.

Quan sát thêm (không sửa gì): vài tài khoản cũ (id 23–31) có mật khẩu dạng văn bản thô `1`; băm SHA1 kép không có salt là yếu –
chỉ nên đổi sang `password_hash()` **khi đã xác nhận server game hỗ trợ**, nên module này giữ nguyên định dạng cũ.
File dump chứa mã băm mật khẩu và số điện thoại thật: đừng gửi công khai.

## 2. Cấu trúc file

```
index.php                    Trang chủ – nạp register.php (để mở thẳng http://localhost/kpah-register/)
register.php                 Trang đăng ký (HTML)
captcha.php                  Ảnh CAPTCHA (PNG, GD, mã lưu trong session)
api/register.php             API xử lý đăng ký (POST → JSON)
includes/bootstrap.php       Khởi động: cấu hình, session, múi giờ, không hiện lỗi PHP
includes/config_loader.php   Nạp cấu hình: mặc định → local → (tuỳ chọn) config.php dự án → biến môi trường
includes/security.php        Header/CSP, session cookie, CSRF, IP client, giới hạn tần suất, escape
includes/db.php              Kết nối PDO (đọc từ cấu hình, không ghi cứng)
includes/captcha.php         Sinh/kiểm tra/vẽ CAPTCHA (PNG nếu có GD, tự chuyển sang SVG nếu không)
includes/captcha_glyphs.php  Nét vẽ ký tự cho CAPTCHA SVG
includes/validator.php       Validate phía server (username, mật khẩu, SĐT, điều khoản)
includes/account.php         Băm mật khẩu + INSERT vào team_user
includes/terms_content.php   Nội dung điều khoản (BẢN MẪU – hãy sửa)
config.register.php          Cấu hình mặc định (không chứa mật khẩu)
config.register.local.sample.php   Mẫu cấu hình bí mật
config.register.local.php    Cấu hình sẵn cho XAMPP (root, không mật khẩu, DB `kpah`) – ĐỔI khi lên hosting
assets/css|js|img|fonts      Giao diện, script, nền SVG, font cho CAPTCHA (DejaVu, giấy phép tự do)
storage/ratelimit/           Dữ liệu giới hạn tần suất (file, tự dọn)
check.php                    Trang chẩn đoán (chỉ localhost, chỉ đọc) – XOÁ khi lên hosting
htaccess-snippet.txt         Đoạn .htaccess gợi ý để GỘP vào file cũ
database/verify_schema.sql   Câu lệnh chỉ-đọc để đối chiếu schema + mẫu cấp quyền DB tối thiểu
```

`index.php` chỉ nạp `register.php`; nếu thư mục đích đã có `index.php` riêng của bạn thì đừng chép đè (dùng `register.php` trực tiếp). Module không tạo `config.php` hay `.htaccess` ở gốc để không đè file của bạn.

## 3. Triển khai

1. **Giải nén** vào thư mục web (gốc hoặc thư mục con đều được – mọi đường dẫn là tương đối). Nếu thư mục đã có file cùng tên, so sánh trước khi chép.
2. **Cấu hình DB:** sao chép `config.register.local.sample.php` → `config.register.local.php`, điền host/tên DB/user/mật khẩu.
   Hoặc dùng biến môi trường `KPAH_DB_HOST`, `KPAH_DB_PORT`, `KPAH_DB_NAME`, `KPAH_DB_USER`, `KPAH_DB_PASS`.
   Nếu `config.php` của bạn `return` một mảng có khoá `db`/`security`/`captcha`, bật `integration.load_project_config = true` để dùng lại (mặc định tắt để tránh xung đột).
3. **Tạo user DB riêng quyền tối thiểu** (`SELECT, INSERT` trên `team_user`) – mẫu trong `database/verify_schema.sql`.
4. **Quyền ghi:** `storage/ratelimit/` phải ghi được bởi PHP (chmod 750/755 tuỳ hosting). Nếu không, module tự dùng thư mục tạm của hệ thống; nếu cả hai đều không ghi được thì **giới hạn tần suất bị bỏ qua** (có ghi log).
5. **Gộp `.htaccess`:** xem `htaccess-snippet.txt`. Các thư mục `includes/`, `storage/`, `database/` đã tự chặn truy cập.
6. **Đường dẫn thật:** điền `links.login` (trang đăng nhập có thật của bạn) và `links.download` trong `config.register.local.php`; để trống thì nút/liên kết tự ẩn – module **không tự bịa đường dẫn đăng nhập**.
7. **PHP:** cần ≥ 7.2, extension `pdo_mysql`, `mbstring`. Extension `gd` không bắt buộc: có GD thì CAPTCHA là ảnh PNG, không có thì tự dùng ảnh SVG (`captcha.driver` = `auto`). Nên chạy HTTPS (cookie tự thêm cờ `Secure` khi phát hiện HTTPS).
8. **Phía sau Cloudflare/proxy:** đặt `security.trusted_ip_header` (vd `HTTP_CF_CONNECTING_IP`) để giới hạn tần suất theo IP thật; chỉ bật khi chắc header do proxy của bạn gán.
9. Truy cập `https://domain-cua-ban/…/register.php`.

## 3a. Chạy trên XAMPP (máy cá nhân)

1. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
2. Vào `http://localhost/phpmyadmin` → import `kpah.sql` (tạo database tên `kpah`).
3. Giải nén thư mục `kpah-register` vào `C:\xampp\htdocs\`.
4. Kiểm tra `config.register.local.php` (đã điền sẵn `root`, mật khẩu rỗng, DB `kpah`; sửa nếu bạn đặt khác).
5. Mở `http://localhost/kpah-register/` (hoặc `.../register.php`).

**Nếu đăng ký báo "Hệ thống đang bận hoặc bảo trì"**: mở `http://localhost/kpah-register/check.php` – trang này kiểm tra từng bước (PHP, pdo_mysql, kết nối DB, bảng `team_user`, cột, ràng buộc) và ghi rõ dòng nào LỖI kèm cách sửa. Lỗi chi tiết cũng được ghi vào `C:\xampp\apache\logs\error.log`. Xoá `check.php` khi xong.

Nếu muốn dùng CAPTCHA ảnh PNG: mở `C:\xampp\php\php.ini`, bỏ dấu `;` trước `extension=gd`, rồi **Restart Apache**. Không làm cũng được vì đã có SVG dự phòng.
Mỗi lần đăng ký thử thành công sẽ tạo dòng thật trong `team_user`; giới hạn mặc định 5 tài khoản/24 giờ/IP – khi thử nhiều, tăng `security.rate_success` hoặc xoá file trong `storage/ratelimit/`.

### Cấu hình chính (`config.register.php`)
- `security.username_pattern` (mặc định `/^[a-z0-9_]{4,20}$/`), `username_hint`, `password_min` = 6, `password_max` = 32.
- `security.rate_attempts` = 10 lượt gửi / 10 phút / IP; `rate_success` = 5 tài khoản / 24 giờ / IP (IP dùng chung như quán net có thể cần tăng).
- `registration.open`, `registration.phone` (`off|optional|required`), `registration.terms_required`.
- `captcha.enabled`, `captcha.driver` (`auto` | `gd` | `svg`). Thiếu GD thì tự dùng SVG, CAPTCHA không bao giờ bị bỏ qua âm thầm.
- `security.use_bad_word_table` (mặc định tắt): chặn tên chứa từ trong bảng `bad_word`; khớp chuỗi con nên có thể chặn nhầm.

## 4. Bảo mật đã áp dụng
Prepared statements (không dùng emulate); CSRF token trong session (xoay sau mỗi lần gửi); CAPTCHA kiểm tra phía server, dùng một lần, hết hạn 5 phút;
honeypot + chặn gửi quá nhanh; giới hạn tần suất theo IP; escape toàn bộ output (`htmlspecialchars`); CSP không cho script/style nội tuyến;
`X-Frame-Options`, `nosniff`, `Referrer-Policy`; cookie session `HttpOnly` + `SameSite=Lax` (+ `Secure` khi HTTPS), tên riêng `KPAHREG`; `session_regenerate_id` sau khi thành công;
lỗi PHP/SQL không hiển thị ra web, log không chứa mật khẩu; mật khẩu giới hạn ASCII in được, không khoảng trắng.
Chưa bật HSTS (tự bật ở web server khi đã chắc chắn dùng HTTPS lâu dài).

## 5. Đã kiểm thử gì – và chưa kiểm thử gì

**Đã chạy thật (trong môi trường của mình):**
- Thuật toán băm mật khẩu so với dữ liệu thật trong `kpah.sql` → khớp.
- JavaScript: kiểm tra cú pháp; 24 test đơn vị cho username/mật khẩu/SĐT/độ mạnh → đạt.
- Giao diện bằng trình duyệt headless (Chromium) với **API giả lập**: validate client, tự viết thường tên tài khoản, hiện/ẩn mật khẩu, thanh độ mạnh, khớp mật khẩu, hộp điều khoản, trạng thái loading, chặn gửi trùng khi bấm liên tục,
  hiển thị lỗi server (CAPTCHA sai, trùng tên), xoá mật khẩu nhưng giữ tên tài khoản, cập nhật CSRF, màn hình thành công, “đăng ký tài khoản khác” → 25/25 đạt.
- Chụp màn hình desktop (1366px), tablet (820px), mobile (390px).
- Rà soát tĩnh PHP: cân bằng ngoặc, các `require` tồn tại, không dùng cú pháp PHP 8-only, mọi hàm `kp_*` được gọi đều có định nghĩa.

**CHƯA chạy được (máy mình không có PHP/MySQL):**
- Chưa thực thi bất kỳ dòng PHP nào, chưa có `php -l`. **Hãy chạy `php -l` cho từng file PHP và thử đăng ký thật trên môi trường staging trước.**
- Chưa chạy thật: INSERT vào MySQL/MariaDB, `GET_LOCK`, vẽ ảnh CAPTCHA bằng GD, giới hạn tần suất bằng file, header/CSP/cookie.
- Giao diện đã xem với font dự phòng (sandbox không tải được Google Fonts); trên mạng thật sẽ dùng Cinzel / Philosopher / Be Vietnam Pro.

### Checklist nghiệm thu trên staging
1. `php -l` mọi file `.php` → không lỗi.
2. Mở `register.php` trên điện thoại và desktop; CAPTCHA hiện ảnh; font tiếng Việt đúng.
3. Đăng ký tên mới hợp lệ → thành công; kiểm tra `SELECT * FROM team_user ORDER BY id DESC LIMIT 1` (mật khẩu 41 ký tự bắt đầu `*`, `userid` = trước đó + 1, `phone` dạng `+84…`).
4. **Đăng nhập vào game bằng tài khoản vừa tạo** – đây là bằng chứng duy nhất cho “server game chấp nhận định dạng”.
5. Đăng ký lại cùng tên → bị từ chối; mật khẩu < 6 hoặc > 32 → bị từ chối; CAPTCHA sai → bị từ chối.
6. Sửa/bỏ token CSRF (DevTools) → bị từ chối (403); gửi > 10 lần/10 phút → 429.
7. Truy cập trực tiếp `/includes/db.php`, `/storage/`, `/database/verify_schema.sql`, `/config.register.local.php` → 403 hoặc trang trống, **không** lộ nội dung.
8. Các route/chức năng cũ của site vẫn chạy sau khi gộp `.htaccess`.
9. Xoá tài khoản test thủ công sau khi thử (module không có chức năng xoá).

## 6. Chưa xác minh được vì thiếu thông tin
- **Server game có đăng nhập đúng bằng băm `PASSWORD()` hay không** (suy ra từ dữ liệu hiện có, nhưng chỉ bước 4 ở trên mới chứng minh).
- Game có cần tạo thêm dòng ở bảng khác khi tạo tài khoản mới không (trong dump, `tob_char` chỉ tạo khi tạo nhân vật nên mình không ghi gì thêm).
- Cách game server tự cấp `userid` khi đăng ký trong game (module dùng `MAX+1` và thử lại khi trùng; nếu game dùng cơ chế khác, hãy báo để chỉnh).
- `config.php`, `index.php`, `admin/`, `api/`, `ip/`, `.htaccess` hiện có; có/không trang đăng nhập; phiên bản PHP của hosting.
- Điều khoản sử dụng, thông tin phiên bản và liên kết chính thức: hiện là bản mẫu/để trống.
- Hình nền là SVG tự vẽ cho module này; nếu bạn có artwork KPAH riêng, thay `assets/img/scene.svg` / `emblem.svg` (giữ tên file).
