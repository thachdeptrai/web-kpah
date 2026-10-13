# Triển khai KPAH với mã nguồn PHP nằm ngoài webroot

> Thay đổi kiến trúc **chưa tự áp dụng cho VPS**. Chỉ chuyển Apache sau khi kiểm tra nhánh triển khai và đã sao lưu. Không import hay sửa SQL.

## Cấu trúc sau khi sắp xếp

```text
web-kpah/                         (C:/xampp/htdocs — private project root)
├── app/
│   ├── config/                   config mặc định + local (không public)
│   ├── includes/                 PDO, bảo mật, session, mail, nghiệp vụ
│   └── pages/                    PHP logic và HTML: admin, api, topup, account
├── public/                       <- APACHE DOCUMENTROOT
│   ├── .htaccess                 route ngắn + luật chặn file nhạy cảm
│   ├── assets/                   CSS, JS, SVG, font được trình duyệt tải
│   ├── admin/                    file PHP vào cửa cho /admin/*
│   ├── api/                      file PHP vào cửa cho /api/*
│   ├── topup/                    file PHP vào cửa cho /topup/*
│   └── index.php, login.php...   file vào cửa ngắn, không chứa nghiệp vụ
├── storage/                      rate-limit + lịch sử nạp (ngoài webroot)
├── database/                     tài liệu/schema SQL, không cho HTTP tải
├── tests/                        PHP regression tests, không public
├── tools/                        chẩn đoán nội bộ (check.php), không public
├── docs/                         hướng dẫn, không public
└── .github/                      CI, không public
```

Các trang `public/*.php` chỉ gọi file tương ứng trong `app/pages/` bằng đường dẫn tuyệt đối của filesystem. Người truy cập HTTP không thể đọc `app/` nếu Apache trỏ **đúng vào `public/`**. URL cũ vẫn giữ: `/`, `/dang-ky`, `/dang-nhap`, `/quen-mat-khau`, `/tai-khoan`, `/nap`, `/nap-the`, `/admin/login.php`, `/api/register.php`, `/topup/card.php`, `/topup/admin/index.php`...

**HTML, CSS, JavaScript và ảnh vẫn được trình duyệt tải nên luôn có thể xem qua DevTools.** Việc đổi webroot chỉ giúp che logic PHP và file dữ liệu máy chủ, không phải mã hóa frontend.

## Chuẩn bị chuyển đổi trên Windows Server / XAMPP

1. Kiểm tra nhánh `refactor/private-webroot`, không chuyển thẳng production nếu chưa thử staging.
2. Sao lưu **cả** `config.register.local.php`, thư mục `storage/` (bao gồm `storage/topup`), cấu hình VirtualHost Apache và database. Lưu bản sao **ngoài `htdocs`**.
3. Đảm bảo `git status` không có sửa đổi local có thể bị ghi đè. Nếu đang triển khai từ repo ở `C:\xampp\htdocs`, có thể fetch nhánh:

   ```bat
   cd C:\xampp\htdocs
   git fetch origin refactor/private-webroot
   git checkout -b refactor/private-webroot --track origin/refactor/private-webroot
   ```

   **Lưu ý:** sau khi checkout nhánh này, PHP entrypoints cũ ở project root đã được chuyển vào `public/`. Website sẽ cần chuyển `DocumentRoot` ngay. Nên thao tác trong giờ bảo trì hoặc làm thử bằng staging VirtualHost trước.

4. Giữ `C:\xampp\htdocs\config.register.local.php` hiện tại. Bộ nạp cấu hình ưu tiên `app/config/config.register.local.php`, sau đó tự đọc file local cũ ở root. **Không chuyển file bí mật vào `public/`.**
5. Kiểm tra PHP CLI:

   ```bat
   C:\xampp\php\php.exe -l app\includes\bootstrap.php
   C:\xampp\php\php.exe -l app\pages\index.php
   C:\xampp\php\php.exe -l app\pages\admin\login.php
   C:\xampp\php\php.exe -l app\pages\topup\admin\grant.php
   C:\xampp\php\php.exe tests\webroot_smoke.php
   C:\xampp\php\php.exe tests\rankings_smoke.php
   C:\xampp\php\php.exe tests\admin_csrf_smoke.php
   ```

## Cấu hình Apache

Mở file VirtualHost thực tế đang phục vụ domain (thường `C:\xampp\apache\conf\extra\httpd-vhosts.conf` hoặc `httpd-ssl.conf`). **Chỉ thay đường dẫn DocumentRoot và Directory của website**, không xóa chứng chỉ SSL hay VirtualHost khác.

Ví dụ trong VirtualHost cho domain:

```apache
DocumentRoot "C:/xampp/htdocs/public"
<Directory "C:/xampp/htdocs/public">
    Options -Indexes -MultiViews
    AllowOverride All
    Require all granted
</Directory>
```

Nếu site có VirtualHost `*:80` và `*:443`, cần bảo đảm **cả hai** trỏ đúng tới `public/`. Nếu Apache dùng cấu hình DocumentRoot chung trong `httpd.conf`, sửa đúng vị trí đó thay vì tạo VirtualHost trùng.

Kiểm tra `LoadModule rewrite_module` đã bật; `AllowOverride All` chỉ áp dụng cho `public`, không mở quyền truy cập thư mục `app/`.

```bat
C:\xampp\apache\bin\httpd.exe -t
```

Chỉ reload/restart Apache khi báo `Syntax OK`. Phía Cloudflare, giữ SSL Full/Full (strict) hiện có; không đổi về Flexible để né lỗi.

## Kiểm tra sau chuyển đổi

Trên máy ngoài VPS:

```bat
curl.exe -I https://nghienkpah.io.vn/
curl.exe -I https://nghienkpah.io.vn/dang-nhap
curl.exe -I https://nghienkpah.io.vn/admin/login.php
curl.exe -I https://nghienkpah.io.vn/assets/css/player-portal.css
```

Các URL trên phải trả mã hợp lệ hoặc chuyển hướng đăng nhập (không phải 404 do sai đường dẫn).

Các URL sau phải trả **403/404**, không được tải về mã PHP/config/dump:

```bat
curl.exe -I https://nghienkpah.io.vn/.git/config
curl.exe -I https://nghienkpah.io.vn/app/includes/db.php
curl.exe -I https://nghienkpah.io.vn/storage/topup/rates.json
curl.exe -I https://nghienkpah.io.vn/database/verify_schema.sql
curl.exe -I https://nghienkpah.io.vn/check.php
```

Kiểm tra thêm bằng trình duyệt: đăng ký, CAPTCHA, login/logout user, profile + OTP, admin login + 2FA, xem Top, topup history, admin topup. **Không sử dụng thẻ cào thật để test.**

Đường dẫn `/topup/card-callback.php` vẫn giữ nguyên; không cần đổi cấu hình callback đối tác.

## Rollback an toàn

Nếu site lỗi sau khi đổi webroot:

1. Dừng nhận thanh toán/ngưng thao tác cộng tài nguyên trong lúc rollback.
2. Khôi phục cấu hình Apache `DocumentRoot "C:/xampp/htdocs"` và `<Directory ...>` ban đầu.
3. Checkout commit/nhánh đang dùng trước khi chuyển đổi, thường `git checkout main`. **Không dùng `git reset --hard` nếu còn file local chưa sao lưu.**
4. Kiểm tra `httpd.exe -t`, restart Apache và thử lại login.
5. Giữ nguyên `storage/` và local config; không xóa lịch sử thanh toán.

## Về yêu cầu không lộ source code

**Repo GitHub `thachdeptrai/web-kpah` hiện đang Public.** Chuyển file PHP ra ngoài Apache webroot **không che source trên GitHub**. Muốn giữ mã PHP không công khai, chủ repo phải vào GitHub → repo Settings → General → Danger Zone → Change repository visibility → **Private**. Trước khi đổi, kiểm tra quy trình `git pull` trên VPS đã có quyền truy cập private repo. Nếu bí mật từng được commit khi repo còn Public, cần thay khóa/mật khẩu và xử lý bản sao/lịch sử; chỉ đổi Private không thu hồi được bản sao người khác đã tải.

Một số đường dẫn cũ bên ngoài vẫn giữ vì cần tương thích; việc đặt toàn bộ logic ở `app/pages` mới là cơ chế bảo vệ server-side. Không có thay đổi SQL trong đợt tái cấu trúc này.
