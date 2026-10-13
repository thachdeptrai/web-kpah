# NGHIỆN KPAH

Website dành cho người chơi KPAH. Tạo tài khoản, xem nhân vật, theo dõi bảng Top và gặp lại anh em trong cộng đồng.

**Stack:** PHP 8 · MariaDB/MySQL · Apache · CSS/JavaScript  
**Website:** [nghienkpah.io.vn](https://nghienkpah.io.vn)

## Mở đúng trang cần dùng

| Người chơi | Đường dẫn |
|---|---|
| Trang chủ | `/` |
| Đăng ký | `/dang-ky` |
| Đăng nhập | `/dang-nhap` |
| Quên mật khẩu | `/quen-mat-khau` |
| Khu tài khoản | `/tai-khoan` |
| Hồ sơ, đổi mật khẩu | `/tai-khoan/ho-so` |
| Nhân vật | `/tai-khoan/nhan-vat` |
| Bảng xếp hạng | `/bang-xep-hang` |
| Chọn cách nạp | `/nap` |
| Nạp thẻ | `/nap/the` |
| Lịch sử nạp | `/nap/lich-su` |
| Nạp ngân hàng | `/nap/ngan-hang` — chưa mở |

**Quản trị:** `/quan-tri` · `/quan-tri/dang-nhap` · `/quan-tri/ma-gioi-thieu` · `/quan-tri/nap` (các mục tỷ lệ, cộng tài nguyên và đối soát có trang riêng).

Bảng Top hiện có các hạng mục Cấp độ, Danh vọng, PK, Bang hội, Nạp Lượng, Điểm sự kiện, Thách đấu, Chiếm thành và Sự kiện WC. Chỉ hiển thị dữ liệu công khai phù hợp; không đưa email, số điện thoại hay số dư riêng của người chơi lên bảng Top.

Đường dẫn `.php` cũ vẫn có lớp tương thích để tránh hỏng bookmark, API và form đã dùng trước đây. Giao diện dùng URL ngắn làm đường dẫn chính.

## Bố cục mã nguồn

```text
app/                 Mã PHP nội bộ (không mở qua HTTP)
  Accounts/          Tài khoản & hồ sơ
  Security/          Session, CSRF, quyền admin, CAPTCHA
  Infrastructure/    Kết nối DB, cấu hình, email
  Community/         Mã giới thiệu
  Game/              Bảng xếp hạng
  Payments/          Nạp và tỷ lệ quy đổi
  Pages/             Nội dung trang công khai
admin/               Endpoint admin tương thích
api/                 Endpoint API hiện có
assets/              CSS, JS, SVG và tài nguyên giao diện
docs/                Hướng dẫn triển khai và vận hành
includes/            Bootstrap và các loader tương thích
storage/             Dữ liệu tạm / nhật ký (chặn HTTP)
tests/               Kiểm thử PHP và HTTP
tools/               Chẩn đoán dùng trên VPS
topup/               Endpoint nạp, callback và admin nạp
.htaccess            Route ngắn + chặn file nội bộ
```

**Không xóa các endpoint cũ chỉ để làm cây thư mục đẹp hơn.** File ở gốc là entrypoint tương thích, còn phần xử lý lớn nằm trong `app/`.

Xem thêm: [Sơ đồ thư mục, route và cách kiểm thử](docs/PROJECT_STRUCTURE.md).

## Cài đặt

Cần Apache 2.4 bật `mod_rewrite`, PHP 8.0+, các extension `pdo_mysql` và cấu hình session hoạt động bình thường. Các luồng OTP cần SMTP; nạp thẻ thực tế cần thêm `curl` cùng tài khoản đối tác.

1. Clone/pull mã nguồn về thư mục website.
2. Tạo `config.register.local.php` dựa trên `config.register.local.sample.php`.
3. Điền database, email và thông số cần thiết. Không commit mật khẩu hoặc khóa API lên Git.
4. Cấu hình `AllowOverride All` cho DocumentRoot, đảm bảo `.htaccess` được Apache áp dụng.
5. Cho phép PHP ghi vào `storage/`; **không** cấp quyền truy cập HTTP tới thư mục này.

Có thể cấu hình DB qua biến môi trường `KPAH_DB_HOST`, `KPAH_DB_PORT`, `KPAH_DB_NAME`, `KPAH_DB_USER`, `KPAH_DB_PASS`.

Đối với site đang chạy: **không import lại `kpah.sql`**. Đợt sắp xếp thư mục và chuẩn hóa URL không thay đổi schema hoặc dữ liệu SQL.

## Cập nhật VPS

Trong thư mục dự án:

```bat
cd C:\xampp\htdocs
git pull --ff-only origin main
C:\xampp\apache\bin\httpd.exe -t
```

Sau khi cập nhật, thử `/dang-nhap`, `/tai-khoan`, `/bang-xep-hang`, `/nap` và `/quan-tri/dang-nhap`. Nếu dùng Cloudflare, tránh cache trang đăng nhập/API, làm mới CSS/JS khi cần. Giữ nguyên `config.register.local.php`.

## Kiểm thử

Kiểm tra cú pháp PHP và route trên máy:

```bat
C:\xampp\php\php.exe tests\site_contract_smoke.php
C:\xampp\php\php.exe tests\admin_csrf_smoke.php
C:\xampp\php\php.exe tests\admin_security_smoke.php
C:\xampp\php\php.exe tests\rankings_smoke.php
C:\xampp\php\php.exe tests\topup_smoke.php
```

GitHub Actions kiểm tra PHP 8.0/8.2, tương thích URL, tệp CSS/JS, cookie, CSRF, quyền truy cập và HTTP smoke. Trên VPS vẫn cần thử lại với Apache, database, SMTP và game server thật; test tự động không thể bảo đảm mọi tình huống production.

## Tình trạng nạp

- Mặc định: **10.000đ = 100 Lượng thường hoặc 200 Lượng khóa**. Admin có thể sửa tỷ lệ.
- Admin cộng thủ công có ghi nhật ký và yêu cầu người chơi thoát game.
- **Nạp thẻ thật chưa bật mặc định.** Cần xác minh đối tác và cách game server xử lý `board_naptien` trước khi mở.
- Ngân hàng chưa tích hợp. Trang hiện tại chỉ thông báo trạng thái, không đưa số tài khoản nhận tiền.

Hướng dẫn riêng: [Nạp thẻ & quản trị](docs/TOPUP_TEAMWORK.md) · [Bảo mật](docs/SECURITY_HARDENING.md).

---

**NGHIỆN KPAH** — [Website](https://nghienkpah.io.vn) · [GitHub](https://github.com/thachdeptrai/web-kpah)
