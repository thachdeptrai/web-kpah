# Bảo mật NGHIỆN KPAH — hướng dẫn vận hành

Tài liệu áp dụng cho repo \`web-kpah\` dùng PHP/MySQL/MariaDB trên Windows Server + Apache/XAMPP (có thể đi qua Cloudflare).

## Phạm vi bản cập nhật

**Đã thay đổi code:**
- Gom kiểm tra quyền truy cập admin về \`includes/admin_security.php\`; các trang admin và trang nạp admin sử dụng cùng cơ chế.
- Kiểm tra quyền từ \`kpah_web_admins\` trong mỗi yêu cầu; giới hạn 30 phút không hoạt động, 8 giờ phiên tối đa, ràng buộc User-Agent và thay ID phiên định kỳ.
- Thêm hỗ trợ TOTP từ Google/Microsoft Authenticator: tùy chọn cấu hình trên VPS, không cần SQL.
- Giới hạn đoán mật khẩu theo IP **và** theo tên admin; không bỏ qua giới hạn khi thư mục lưu bị lỗi.
- CSRF token, xác thực role và kiểm tra trạng thái active vẫn giữ nguyên.
- Ngăn truy cập HTTP vào \`.git\`, \`storage/\`, \`includes/\`, \`database/\`, \`tests/\`, \`docs/\`, bản dump, file backup và \`check.php\`.
- PDO dùng prepared statements thật (\`ATTR_EMULATE_PREPARES=false\`), vô hiệu hóa multi-statements khi driver hỗ trợ.
- Bổ sung headers \`Referrer-Policy: no-referrer\`, \`nosniff\`, CSP, và HSTS khi PHP nhận ra kết nối HTTPS.

**Không thay đổi schema, không import SQL, không cập nhật/xóa dữ liệu game.**

## Bật xác thực hai bước cho admin

Mặc định: **đang chạy tương thích với mật khẩu hiện tại**. Để kích hoạt TOTP, lưu secret ở cấu hình máy chủ **ngoài thư mục htdocs**, không lưu vào Git hoặc database.

1. Cài ứng dụng Authenticator trên điện thoại.
2. Trên VPS, tạo secret Base32 ngẫu nhiên cho **từng admin**. Có thể dùng PHP CLI:

   \`\`\`bat
   C:\xampp\php\php.exe -r "$a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; for($i=0;$i<32;$i++){echo $a[random_int(0,31)];}"
   \`\`\`

3. Thêm secret thủ công vào Authenticator dưới dạng **Time-based**, 6 chữ số, chu kỳ 30 giây.
4. Trong cấu hình Apache VirtualHost (chẳng hạn \`C:\xampp\apache\conf\extra\httpd-vhosts.conf\`, **không phải file public**), thêm vào virtual host chạy site:

   \`\`\`apache
   SetEnv KPAH_ADMIN_REQUIRE_TOTP 1
   SetEnv KPAH_ADMIN_TOTP_SECRET "SECRET_BASE32_CUA_BAN"
   \`\`\`

   \`KPAH_ADMIN_TOTP_SECRET\` dùng chung toàn bộ admin, chỉ hợp lý nếu website có **một** admin. Khi có nhiều admin, dùng \`KPAH_ADMIN_TOTP_SECRETS\` dưới dạng JSON ánh xạ username => secret, với mỗi admin một secret khác nhau. Người không nằm trong ánh xạ **không được bỏ qua 2FA**.

5. Kiểm tra cú pháp Apache, restart Apache rồi truy cập \`/admin/login.php\`: phải có ô mã xác thực 6 số. Thử một mã sai và một mã đúng trước khi dùng production lâu dài.

**Quan trọng:** Đừng bật \`KPAH_ADMIN_REQUIRE_TOTP=1\` khi chưa thêm secret vào ứng dụng Authenticator. Nếu thất lạc điện thoại và không có backup, bạn phải tắt cấu hình trên VPS để khôi phục quyền truy cập. Không có backdoor bỏ qua OTP.

### Giới hạn IP admin (tùy chọn)

Có thể thiết lập:

\`\`\`apache
SetEnv KPAH_ADMIN_ALLOWED_IPS "203.0.113.10,2001:db8::10"
\`\`\`

Chỉ cho các địa chỉ IP chính xác trong danh sách vào admin. Nếu IP thay đổi hoặc PHP nhìn thấy IP Cloudflare thay cho IP người dùng, **không bật tính năng này** trước khi đối chiếu \`REMOTE_ADDR\`. Nếu cấu hình sai, bạn có thể tự khóa quyền truy cập.

Nên triển khai **Cloudflare Access** để bắt đăng nhập qua email/OTP hoặc khóa bảo mật trước khi truy cập \`/admin/*\` và \`/topup/admin/*\`. Access không thay thế xác thực của PHP.

### Hướng dẫn cookie HTTPS

Xem DevTools > Application > Cookies: session phải có \`HttpOnly\`, \`SameSite=Lax\`, \`Secure\` khi site dùng HTTPS. Đảm bảo XAMPP/PHP nhận đúng trạng thái HTTPS nếu có reverse proxy. Chỉ bật \`security.trust_forwarded_proto\` khi **proxy đáng tin cậy** gán header và người dùng không thể kết nối trực tiếp tới origin để giả mạo.

## Chặn dump, backup, \`.git\` qua web

Root \`.htaccess\` đã có luật bảo vệ. Tuy nhiên **Apache phải bật \`mod_rewrite\` và AllowOverride phù hợp**; nếu \`.htaccess\` bị bỏ qua, các luật không có hiệu lực.

Từ máy khác (không phải VPS), kiểm tra:

\`\`\`bat
curl.exe -I https://nghienkpah.io.vn/.git/config
curl.exe -I https://nghienkpah.io.vn/database/referral_codes.sql
curl.exe -I https://nghienkpah.io.vn/storage/topup/rates.json
curl.exe -I https://nghienkpah.io.vn/config.register.local.php
curl.exe -I https://nghienkpah.io.vn/check.php
\`\`\`

Kết quả kỳ vọng: **403 hoặc 404** (có thể qua Cloudflare). Nếu 200, **dừng triển khai và sửa Apache/Cloudflare**.

Tránh để các file \`*.zip\` chứa backup SQL hoặc toàn bộ mã nguồn dưới \`htdocs\`. Luật .htaccess không bảo vệ được mọi tên file nén tự đặt; cách đúng là chuyển backup ra ngoài DocumentRoot.

Việc dùng Git clone trong \`htdocs\` có thể tạo thư mục \`.git\`. Các luật mới chặn HTTP vào \`.git\`, nhưng cách an toàn hơn vẫn là deploy từ thư mục build/release không chứa Git hoặc secrets.

## SQL Injection và quyền database

- Giữ toàn bộ giá trị đầu vào của người dùng ở tham số \`PDO::prepare(...)->execute([...])\`. **Không** nối \`$_GET\`/\`$_POST\` vào SQL, \`ORDER BY\`, tên bảng hoặc tên cột. Với tên cột động, bắt buộc whitelist cố định.
- \`ATTR_EMULATE_PREPARES=false\` và tắt multi-statements hỗ trợ giảm nguy cơ stacked query; **không thay thế việc kiểm tra từng truy vấn**.
- **Không dùng MariaDB user \`root\` cho web** ở production. Tạo user ứng dụng với quyền tối thiểu theo các bảng mà website thật sự truy cập; không cấp \`FILE\`, \`SUPER\`, \`CREATE USER\`, \`GRANT OPTION\`, \`DROP\`, \`ALTER\` cho PHP.
- Không để MySQL/MariaDB mở TCP 3306 ra Internet. Nếu server game kết nối từ máy khác, chỉ cho IP/server cần dùng đi qua firewall.
- Tuyệt đối không commit \`kpah.sql\`, file export Navicat, \`.env\`, \`config.register.local.php\` hoặc secret API lên GitHub. Nếu từng bị public, **thay mật khẩu/khóa**, xóa bản public và kiểm tra lịch sử Git; chỉ xóa file ở commit mới không xóa được lịch sử cũ.
- Sử dụng HTTPS và Cloudflare WAF/rate limiting cho \`/admin/login.php\`, \`/api/*\`, \`/topup/*\` để tăng lớp bảo vệ.

Không thể khẳng định “không có SQL Injection” nếu chưa chạy kiểm thử tích hợp toàn bộ endpoint với dữ liệu production giả lập. Nếu có bất thường, đối chiếu log máy chủ và log database.

## Sau khi cập nhật

1. Sao lưu code, cấu hình private và database.
2. \`git pull --ff-only origin main\` tại thư mục website.
3. Kiểm tra Apache và PHP. \`storage/ratelimit\` và \`storage/topup\` phải cho PHP đọc/ghi, nhưng chặn HTTP.
4. Đăng nhập admin: phiên admin cũ sẽ yêu cầu đăng nhập lại vì định dạng session đã nâng cấp.
5. Thử đăng nhập đúng, sai, hết hạn; mở \`/admin/index.php\`, \`/admin/referrals.php\`, \`/topup/admin/index.php\`, \`/topup/admin/grant.php\`.
6. Kiểm tra URL nhạy cảm trả 403/404; kiểm tra đăng ký, đăng nhập người chơi, OTP và giao diện.
7. Nếu nhận HTTP 503/429 từ trang đăng nhập do không ghi được limiter, sửa quyền thư mục \`storage/ratelimit\` — **không tắt rate limit để né lỗi**.

### Chạy kiểm tra với PHP CLI

\`\`\`bat
C:\xampp\php\php.exe -l includes\admin_security.php
C:\xampp\php\php.exe -l admin\login.php
C:\xampp\php\php.exe tests\admin_security_smoke.php
\`\`\`

GitHub Actions workflow \`.github/workflows/security-checks.yml\` cũng chạy lint toàn bộ PHP và các bài thử 2FA, replay, throttling khi thay đổi code liên quan.

## Giới hạn còn lại

- Bảo vệ HTTP không thể ngăn người đã chiếm được quyền Windows/VPS hoặc MySQL user đủ mạnh đọc/dump database.
- Cloudflare/WAF không thay cho PDO prepared statements và quyền SQL tối thiểu.
- TOTP là lựa chọn tăng cường, **chưa tự bật** khi chưa có secret trong môi trường máy chủ.
- Phần cộng Lượng trực tiếp và tự động cần kiểm chứng riêng cơ chế game server, nhất là MyISAM, cache nhân vật và chống cấp trùng.
- Bản cập nhật repo không đồng nghĩa đã pentest production hoặc kiểm toán được firewall/Cloudflare. Các bước kiểm tra server thật phải do người quản trị xác nhận.


## Sửa lỗi đăng nhập admin báo "Phiên làm việc hết hạn"

**Đã xử lý trong code:** admin chuyển sang cookie phiên riêng `KPAHADMIN`, trang người chơi giữ `KPAHREG`. Nhờ vậy thao tác đăng nhập người chơi không làm mất mã CSRF ở biểu mẫu admin đang mở. Cả `/admin/*.php` và `/topup/admin/*.php` dùng chung phiên admin; logout admin không phải logout người chơi.

CSRF vẫn bắt buộc token từ session phía server. Kiểm tra `Sec-Fetch-Site` ngăn yêu cầu khác origin. Khi Cloudflare/reverse proxy chuyển tiếp với hostname nội bộ, request có `Sec-Fetch-Site: same-origin` của trình duyệt vẫn hợp lệ. Nếu cần hỗ trợ trình duyệt cũ và Apache nhận `HTTP_HOST` khác tên miền công khai, thiết lập `KPAH_PUBLIC_HOST=nghienkpah.io.vn` ngoài source và Git, trong cấu hình VirtualHost của VPS. Không dùng `X-Forwarded-Host` chưa xác minh làm bằng chứng cùng origin.

### Cách triển khai

1. Sao lưu `config.register.local.php` và pull code về VPS.
2. Mở tab mới tại `https://nghienkpah.io.vn/admin/login.php` và tải lại mạnh (`Ctrl+Shift+R`), **không gửi lại biểu mẫu admin đã mở trước khi pull**; biểu mẫu cũ dùng cookie `KPAHREG`.
3. Đăng nhập lại một lần với mật khẩu (và OTP nếu đã bật MFA). Session admin trước khi cập nhật không được giữ lại; đây là thay đổi có chủ ý.
4. Kiểm tra trong DevTools → Application → Cookies có cookie `KPAHADMIN` với `HttpOnly`, `SameSite=Lax` và `Secure` nếu PHP nhận dạng kết nối HTTPS. Cookie của người chơi `KPAHREG` vẫn độc lập.
5. Nếu còn báo lỗi, xem `C:\xampp\apache\logs\error.log` (hoặc PHP error log đã cấu hình) với tiền tố `[KPAH] admin_security login_csrf_...` để phân biệt mất cookie, token cũ hoặc nguồn yêu cầu bị chặn. Không gửi cookie, CSRF token hay OTP vào GitHub Issue.
6. Nếu xuất hiện `session_start failed`, sửa quyền ghi `session.save_path` cho tài khoản chạy Apache/PHP; hệ thống **không được tắt CSRF** để né lỗi. Đồng thời tránh Cloudflare Cache Everything cho `/admin/*` và `/topup/admin/*`.

Kiểm tra không kết nối SQL:

```bat
C:\xampp\php\php.exe tests\admin_csrf_smoke.php
```

Bài kiểm tra bảo đảm phiên admin/người chơi có tên cookie khác nhau, token CSRF được kiểm tra và chặn các yêu cầu cross-site, đồng thời hỗ trợ reverse proxy. Chưa thể xác nhận tình trạng cookie/session lưu thực tế trên VPS từ riêng mã nguồn repo.
