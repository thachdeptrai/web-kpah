# NGHIỆN KPAH

Website cộng đồng **NGHIỆN KPAH** — quản lý tài khoản và nhân vật, bảng xếp hạng, nạp tiền vào ví VNĐ, đổi tài nguyên, tích Point đổi quà và quản trị hệ thống.

[**Truy cập website**](https://nghienkpah.io.vn) · [**Mã nguồn GitHub**](https://github.com/thachdeptrai/web-kpah)

**Stack:** PHP 8.0+ · MariaDB/MySQL (PDO) · Apache 2.4/XAMPP · HTML/CSS/JavaScript · GitHub Actions.

> Đây là mã nguồn **website**, không phải game server. Những thao tác cấp tài nguyên vào nhân vật cần đối chiếu với dữ liệu và trạng thái game thực tế. Tính năng thanh toán chỉ hoạt động khi đã cấu hình nhà cung cấp, database và quyền ghi phù hợp.

## Chức năng hiện có

| Khu vực | Nội dung |
| --- | --- |
| Trang chủ | Giới thiệu, thông tin cộng đồng, liên kết tới các khu vực của website |
| Tài khoản | Đăng ký, đăng nhập, khôi phục mật khẩu, xác minh email và cập nhật hồ sơ |
| Tổng quan | Ví VNĐ, tổng nạp, Point, nhân vật thuộc tài khoản, giao dịch gần đây, thông tin bảo mật và thành tích khi có dữ liệu |
| Nhân vật | Cấp độ, kinh nghiệm, Xu, Lượng, Lượng khóa và bang hội từ dữ liệu game |
| Xếp hạng | Các danh mục Top nạp, tài sản, cấp độ, VIP, chiến trường, nông trại…; chỉ hiện mục có dữ liệu phù hợp |
| Nạp thẻ | Tích hợp API đối tác thẻ; thẻ hợp lệ cộng **VNĐ thực nhận sau chiết khấu** vào ví |
| Nạp ngân hàng | payOS/VietQR; cộng ví VNĐ khi xác minh giao dịch, thưởng ngân hàng theo cấu hình |
| Ví VNĐ | Đổi mốc Xu/Lượng/Lượng khóa cho nhân vật đủ điều kiện; cần xác nhận nhân vật đã thoát game |
| Point | Tích điểm từ giao dịch nạp hợp lệ, xem số dư và gửi yêu cầu đổi quà |
| Quản trị | Điều hướng chung, quản lý nạp/đối soát, tỷ lệ quy đổi, thưởng ngân hàng, Point và quà, mã giới thiệu |
| Role `key` | Chỉ truy cập phần mã giới thiệu được phân quyền; không có quyền admin toàn hệ thống |

Giao diện có stylesheet riêng cho desktop và mobile. Các thông tin riêng tư của người chơi được truy vấn theo tài khoản đăng nhập, không dùng bảng xếp hạng công khai để hiển thị dữ liệu cá nhân.

## Đường dẫn chính

### Người chơi

| URL | Chức năng |
| --- | --- |
| `/` | Trang chủ |
| `/dang-ky`, `/dang-nhap`, `/quen-mat-khau` | Đăng ký, đăng nhập, khôi phục |
| `/tai-khoan` | Dashboard tài khoản |
| `/ho-so`, `/nhan-vat` | Hồ sơ và nhân vật |
| `/bang-xep-hang` | Bảng xếp hạng |
| `/nap` | Chọn phương thức nạp |
| `/nap-the`, `/nap-ngan-hang` | Nạp thẻ và ngân hàng |
| `/vi-vnd` | Số dư VNĐ và đổi mốc |
| `/diem-tich-luy` | Point và đổi quà |
| `/lich-su-nap`, `/top-nap` | Lịch sử và Top nạp |

### Quản trị

| URL | Chức năng |
| --- | --- |
| `/quan-tri/dang-nhap` | Đăng nhập admin/key |
| `/quan-tri` | Tổng quan quản trị (**admin**) |
| `/quan-tri/ma-gioi-thieu` | Mã giới thiệu (**admin/key**, theo quyền) |
| `/quan-tri/tai-khoan-key` | Tạo/khóa tài khoản role key (**admin**) |
| `/quan-tri/nap` | Quản lý nạp (**admin**) |
| `/quan-tri/ty-le` | Tỷ lệ quy đổi và thưởng ngân hàng (**admin**) |
| `/quan-tri/cong-tai-nguyen` | Cộng tài nguyên thủ công (**admin**) |
| `/quan-tri/doi-soat` | Kiểm tra giao dịch (**admin**) |
| `/quan-tri/diem-tich-luy` | Quản lý Point và quà (**admin**) |

Các route đã gỡ như `/quan-tri/nap-game` và `/quan-tri/cong-tong-nap` **không còn là trang quản trị**.

Định tuyến tập trung tại `config/routes.php` qua `.htaccess` và `index.php` ở thư mục gốc. Các alias cũ được xử lý theo cấu hình; API/callback không được tự ý redirect làm thay đổi phương thức HTTP. Xem [docs/ROUTING.md](docs/ROUTING.md).

## Luồng nạp tiền và Point

1. Người chơi tạo yêu cầu nạp thẻ hoặc đơn payOS.
2. Backend xác minh kết quả từ nhà cung cấp; **không coi việc mở QR hoặc redirect là đã thanh toán**.
3. Nạp thẻ thành công cộng số **VNĐ sau chiết khấu** vào `team_user.vnd`. Nạp ngân hàng cộng mệnh giá và **thưởng ngân hàng** theo cấu hình tại thời điểm tạo đơn.
4. `team_user.tongnap` ghi nhận **mệnh giá nạp gốc** (ví dụ nạp thẻ 10.000đ, thực nhận 8.000đ thì tổng nạp vẫn tăng 10.000đ), theo luồng ghi nhận giao dịch hợp lệ.
5. Người chơi dùng ví VNĐ để đổi mốc tài nguyên; Point được ghi nhận riêng và có thể đổi quà.

**Mặc định thưởng ngân hàng: +10%**, admin có thể chỉnh tại `/quan-tri/ty-le`. Cấu hình được lưu ở `storage/topup/bank-bonus.json`.

**Tỷ lệ Point mặc định:** 1 Point / 1.000 VNĐ nạp hợp lệ, tương đương **10.000đ = 10 Point**. Tỷ lệ thực tế lấy từ `kp_points_program.points_per_1000`. Point chỉ tính cho giao dịch đủ điều kiện sau thời điểm kích hoạt chương trình, có sổ cái chống ghi trùng. Quà đổi được tạo thành yêu cầu để admin xử lý, không tự phát vật phẩm game.

Hệ thống có cơ chế chống gửi lặp, nhật ký ví, trạng thái đối soát và xử lý các kết quả chưa chắc chắn. **Không tự ý sửa trạng thái `completed`, cộng lại tiền hoặc xóa nhật ký khi chưa đối chiếu với nhà cung cấp.** Trạng thái thanh toán thành công và việc giao tài nguyên game là hai việc cần phân biệt.

Tài liệu nghiệp vụ: [Ví VNĐ & role key](docs/VND_WALLET_KEY.md) · [payOS](docs/PAYOS.md) · [API thẻ](docs/CARD_API.md) · [Point](docs/LOYALTY_POINTS.md).

## Database và cấu hình

Các bảng/cột chính mà code hiện tại sử dụng:

| Nguồn | Vai trò |
| --- | --- |
| `team_user` | Tài khoản; các cột `vnd`, `tongnap` |
| `tob_web_info_char`, `tob_char`, `tob_char_luong` | Liên kết và dữ liệu nhân vật |
| `kp_bank_orders`, `kp_bank_webhook_events` | Đơn payOS và sự kiện callback |
| `kp_points_program`, `kp_points_wallet`, `kp_points_ledger` | Cấu hình, số dư và lịch sử Point |
| `kp_points_rewards`, `kp_points_redemptions` | Quà và yêu cầu đổi |
| `kpah_web_admins` | Tài khoản admin/key |
| `kpah_referral_codes`, `kpah_referral_code_uses` | Mã giới thiệu |
| `storage/topup/` | Cấu hình, nhật ký ví VNĐ và trạng thái nghiệp vụ cần bảo toàn |

**Không import lại SQL nếu các bảng đã tồn tại.** Các file trong `database/` dành cho kiểm tra schema hoặc thiết lập môi trường còn thiếu, **không phải lệnh phải chạy sau mỗi lần `git pull`**. Đặc biệt, ví VNĐ hiện dùng cột `team_user.vnd` và nhật ký riêng, không cần tự tạo thêm bảng ví VNĐ.

**Chú ý liên kết ID:** `tob_web_info_char.userid` tương ứng `team_user.id` trong luồng website hiện tại; không tự thay bằng `team_user.userid`.

Cấu hình riêng đặt ngoài Git:

- `config/register.local.php` — database, SMTP và cấu hình website; mẫu `config/register.local.sample.php`.
- `config/payos.local.php` — khóa payOS; mẫu `config/payos.local.sample.php`.
- `config/topup.local.php` — cấu hình API thẻ; dùng file mẫu tương ứng nếu có.
- Biến môi trường database: `KPAH_DB_HOST`, `KPAH_DB_PORT`, `KPAH_DB_NAME`, `KPAH_DB_USER`, `KPAH_DB_PASS`.

**Không commit mật khẩu, API key, SQL dump, `.env` hoặc nhật ký giao dịch.** Không xóa `storage/topup/` khi deploy; cần sao lưu **cả database lẫn storage** để giữ dữ liệu đối soát.

## Cài đặt trên Windows Server / XAMPP

**Yêu cầu:** PHP 8.0+ với PDO MySQL, Apache 2.4 (`mod_rewrite`, `AllowOverride All`), MariaDB/MySQL, HTTPS; cần cURL/OpenSSL khi dùng nhà cung cấp thanh toán. DocumentRoot trỏ đến thư mục gốc repo (chứa `index.php`).

Ví dụ cập nhật:

```bat
cd C:\xampp\htdocs
git pull --ff-only origin main
```

Sau khi cập nhật:

1. Sao lưu database, cấu hình local và `storage/topup/` trước khi thay đổi nghiệp vụ nạp.
2. Kiểm tra Apache/PHP, quyền ghi `storage/` và các biến môi trường trên VPS.
3. Tải lại website bằng `Ctrl + F5` nếu CSS/JS còn cache.
4. Kiểm tra đăng nhập, hồ sơ, ví VNĐ, các trang nạp, quyền admin/key và giao diện điện thoại.

Với payOS, thiết lập webhook `/api/payos-webhook` và task dọn đơn theo [docs/PAYOS.md](docs/PAYOS.md). **Không dùng tiền thật để thử callback hoặc thay đổi schema chưa kiểm chứng.**

## Cấu trúc dự án

```text
web-kpah/
├── index.php                   # Front controller, giữ ở root cho XAMPP
├── .htaccess                   # Rewrite và chặn truy cập nội bộ
├── .gitattributes              # GitHub Linguist nhận diện PHP/CSS/JS
├── config/                     # Routes và cấu hình
├── app/
│   ├── pages/                  # Trang người chơi
│   ├── admin/                  # Quản trị và role key
│   ├── topup/                  # Nạp thẻ, payOS, ví VNĐ, Point
│   ├── api/                    # API tài khoản
│   └── includes/               # Nghiệp vụ, bảo mật, DB, session
├── assets/                     # CSS, JavaScript, ảnh, font
├── database/                   # SQL thiết lập/kiểm tra schema
├── docs/                       # Tài liệu chi tiết
├── storage/                    # Nhật ký và cấu hình riêng, cần bảo vệ
├── tests/                      # Smoke, HTTP regression, fixture database
├── tools/                      # Công cụ bảo trì chạy nội bộ
└── .github/workflows/          # CI kiểm thử tự động
```

## Kiểm thử và GitHub Actions

**Giữ nguyên bộ test trong repo** để tránh hồi quy khi sửa code. Hiện có 5 workflow:

| Workflow | Phạm vi |
| --- | --- |
| `php-js-syntax.yml` | Kiểm tra cú pháp PHP/JavaScript |
| `player-portal-checks.yml` | Tài khoản, nhân vật, bảng xếp hạng |
| `routing-regression.yml` | HTTP/route/session trên PHP 8.0, PHP 8.3 và Apache với database fixture |
| `security-checks.yml` | Phân quyền admin, CSRF, TOTP, file nhạy cảm |
| `topup-lint.yml` | Cú pháp và logic kiểm tra nạp |

Chạy smoke test trên môi trường phát triển có PHP CLI:

```sh
for test in tests/*_smoke.php; do php "$test" || exit 1; done
```

Trên Windows CMD có thể chạy từng file, ví dụ:

```bat
C:\xampp\php\php.exe tests\topup_smoke.php
C:\xampp\php\php.exe tests\security_smoke.php
C:\xampp\php\php.exe tests\responsive_smoke.php
```

**Integration test chỉ dùng database riêng `kpah_route_test`**, không chạy script `tests/seed_fixture.php` trên database production. Xem [docs/TESTING.md](docs/TESTING.md) để chạy đầy đủ.

File `.gitattributes` ép GitHub Linguist nhận diện PHP đúng và loại file Python trong `tests/` khỏi **biểu đồ tỷ lệ ngôn ngữ**, nhưng **không xóa Python hay tắt test**.

## Bảo mật và giới hạn

- Trang quản trị kiểm tra quyền ở backend, không chỉ ẩn menu. Role `key` bị chặn ở các trang admin không được cấp quyền.
- Dùng CSRF, giới hạn tần suất, session và kiểm tra quyền sở hữu dữ liệu; admin có hỗ trợ TOTP theo cấu hình.
- Không công khai dump SQL, khóa thanh toán, file local hay `storage/`.
- Các bài test tự động **không thay thế** kiểm thử giao dịch thật, callback, Cloudflare, HTTPS, XAMPP và giao diện trực quan trên thiết bị thực tế.

Tài liệu: [Bảo mật](docs/SECURITY_HARDENING.md) · [Triển khai URL](docs/ROUTING.md) · [Kiểm thử](docs/TESTING.md).

---

**NGHIỆN KPAH** · [nghienkpah.io.vn](https://nghienkpah.io.vn) · [GitHub](https://github.com/thachdeptrai/web-kpah)
