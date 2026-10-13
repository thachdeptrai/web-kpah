# Cấu trúc và URL — NGHIỆN KPAH

Mục tiêu: mã nghiệp vụ có nơi rõ ràng, đường dẫn người dùng dễ nhớ, **giữ tương thích ngược** với link cũ. Không sửa bảng hay dữ liệu SQL.

## Cấu trúc

```text
web-kpah/
├── app/                         PHP nội bộ, Apache cấm truy cập HTTP
│   ├── Accounts/                Tài khoản, hồ sơ và giao diện người chơi
│   ├── Community/               Mã giới thiệu
│   ├── Game/                    Truy vấn bảng xếp hạng
│   ├── Infrastructure/          Cấu hình, PDO và mail
│   ├── Pages/                   Nguồn HTML/PHP của 8 trang công khai
│   ├── Payments/                Luồng nạp và tỷ lệ
│   └── Security/                Session, CSRF, CAPTCHA, quyền admin
├── admin/                       Endpoint admin cũ (chưa xóa để tránh lỗi)
├── api/                         Endpoint API cũ + route mới, cùng backend
├── assets/                      CSS, JS, SVG
├── database/                    SQL hiện có, không thay đổi
├── docs/                        Tài liệu vận hành
├── includes/                    Bootstrap và loader tương thích ngược
├── storage/                     Nhật ký/trạng thái nội bộ, không công khai
├── tests/                       Kiểm thử PHP + HTTP
├── tools/diagnostics/           Chẩn đoán VPS (chỉ cho CLI)
├── topup/                       Endpoint nạp, khu admin nạp và callback
├── .htaccess                    Bộ URL duy nhất và chặn file nội bộ
├── index.php, login.php, ...    Wrapper tương thích cho liên kết/form cũ
└── README.md
```

**Tại sao vẫn còn vài file .php ở thư mục gốc?** Đây là *entrypoint* cho server cũ và các form đã phát hành. Toàn bộ nội dung trang được gom vào `app/Pages/`; wrapper nhỏ cho phép liên kết cũ hoạt động. Không xóa hoặc di chuyển API callback nhạy cảm khi chưa kiểm thử end-to-end.

## Đường dẫn chuẩn

| Chức năng | URL |
|---|---|
| Trang chủ | `/` |
| Đăng ký | `/dang-ky` |
| Đăng nhập | `/dang-nhap` |
| Quên mật khẩu | `/quen-mat-khau` |
| Tổng quan tài khoản | `/tai-khoan` |
| Hồ sơ & mật khẩu | `/tai-khoan/ho-so` |
| Nhân vật | `/tai-khoan/nhan-vat` |
| Các bảng Top | `/bang-xep-hang` |
| Chọn cách nạp | `/nap` |
| Nạp thẻ | `/nap/the` |
| Ngân hàng (chưa mở) | `/nap/ngan-hang` |
| Lịch sử nạp | `/nap/lich-su` |
| Admin | `/quan-tri` |
| Đăng nhập admin | `/quan-tri/dang-nhap` |
| Mã giới thiệu | `/quan-tri/ma-gioi-thieu` |
| Quản lý nạp | `/quan-tri/nap` |
| Tỷ lệ nạp | `/quan-tri/nap/ti-le` |
| Cộng tài nguyên | `/quan-tri/nap/cong-tai-nguyen` |
| Đối soát | `/quan-tri/nap/doi-soat` |
| Xem dữ liệu nạp game | `/quan-tri/nap/du-lieu-game` |

API tương ứng cũng có alias sạch, ví dụ `/api/dang-ky` và `/api/tai-khoan/mat-khau`. **Những endpoint API PHP cũ và callback nạp thẻ vẫn hoạt động** nhằm tránh phá vỡ kết nối có sẵn.

## Tương thích ngược

- GET/HEAD tới trang PHP công khai cũ được chuyển hướng **302** tới URL sạch, giữ query string.
- POST gửi trực tiếp tới PHP cũ **không redirect**, vì redirect có thể làm mất body và CSRF. API được giữ lại.
- Route sạch rewrite nội bộ tới cùng file PHP. Không đưa nội dung thật của `app/` ra HTTP.
- `includes/*.php` cũ là loader vào `app/`, giữ tên hàm và chỗ gọi cũ. Bootstrap vẫn giữ nguyên đường dẫn.
- Các link CSS/JS/form mới đều dùng URL tuyệt đối tính từ gốc domain (`/assets/...`), tránh hỏng khi trang nằm dưới `/tai-khoan/...` hay `/quan-tri/nap/...`.

## Kiểm thử

```bat
C:\xampp\php\php.exe tests\site_contract_smoke.php
C:\xampp\php\php.exe tests\admin_security_smoke.php
C:\xampp\php\php.exe tests\admin_csrf_smoke.php
C:\xampp\php\php.exe tests\rankings_smoke.php
C:\xampp\php\php.exe tests\topup_smoke.php
```

GitHub Actions `site-refactor-tests.yml` kiểm tra PHP 8.0/8.2, cú pháp toàn bộ PHP, đường dẫn, liên kết, giới hạn file riêng tư, cookie admin, CSRF, alias cũ và tải tài nguyên CSS/JS.

HTTP smoke dùng **PHP development server**, không thay thế thử nghiệm mod_rewrite trên Apache/XAMPP thật. Trước khi deploy nên:

- Sao lưu file cấu hình và mã hiện tại, chạy `httpd.exe -t`.
- Kiểm tra `mod_rewrite` đang bật, `AllowOverride All` áp dụng cho DocumentRoot.
- Mở các URL chuẩn, kiểm tra GET/POST đăng nhập, đăng ký, OTP, đổi mật khẩu, bảng Top, nạp và các trang quản trị.
- Kiểm tra mobile/PC, CSS/JS và Cloudflare cache.
- Mở `/.git/config`, `/app/Pages/Login.php`, `/storage/...` — phải trả về 403/404.
- Thử với tài khoản game giả lập; **không dùng thẻ thật** khi chưa xác minh API đối tác và game server.

Không thể hứa mọi tương tác production đều đúng nếu chưa có staging tương đương VPS, database, SMTP và game server.

## Khôi phục nhanh

Thay đổi nằm trên PR riêng để có thể review; giữ nguyên dữ liệu database. Nếu lỗi trên staging, checkout commit trước refactor để quay về cấu trúc cũ. **Không chạy lại SQL dump và không force-push khi VPS có sửa đổi chưa commit.**
