# NGHIỆN KPAH

[![PHP & JS syntax](https://github.com/thachdeptrai/web-kpah/actions/workflows/php-js-syntax.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/php-js-syntax.yml)
[![Security regression](https://github.com/thachdeptrai/web-kpah/actions/workflows/security-checks.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/security-checks.yml)
[![Vibe Check](https://github.com/thachdeptrai/web-kpah/actions/workflows/vibe-security-audit.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/vibe-security-audit.yml)

Website cộng đồng **NGHIỆN KPAH** — quản lý tài khoản và nhân vật, bảng xếp hạng, nạp tiền vào ví VNĐ, đổi tài nguyên, tích Point đổi quà và quản trị hệ thống.

[**Truy cập website**](https://nghienkpah.io.vn) · [**Mã nguồn GitHub**](https://github.com/thachdeptrai/web-kpah)

**Stack:** PHP 8.0+ · MariaDB/MySQL (PDO) · Apache 2.4/XAMPP · HTML/CSS/JavaScript · GitHub Actions.

**Truy cập nhanh:** [Chức năng](#chức-năng-hiện-có) · [Đường dẫn](#đường-dẫn-chính) · [Cài đặt XAMPP](#cài-đặt-trên-windows-server--xampp) · [Kiểm thử](#kiểm-thử-và-github-actions) · [Bảo mật](#bảo-mật-và-giới-hạn)

> Đây là mã nguồn **website**, không phải game server. Những thao tác cấp tài nguyên vào nhân vật cần đối chiếu với dữ liệu và trạng thái game thực tế. Tính năng thanh toán chỉ hoạt động khi đã cấu hình nhà cung cấp, database và quyền ghi phù hợp.

## Cập nhật bảo mật — 15/10/2026

Bản cập nhật [Security Audit — PR #13](https://github.com/thachdeptrai/web-kpah/pull/13) đã được merge vào `main`. Bản cập nhật này **không thay đổi schema database, số dư, tỷ lệ nạp/đổi, giao diện hay hệ thống route**.

- **Tài khoản bị khóa:** đăng nhập, cập nhật hồ sơ và luồng đổi/khôi phục mật khẩu đều kiểm tra trạng thái `team_user.ban` ở các thao tác liên quan.
- **Đổi/khôi phục mật khẩu:** dùng cập nhật có điều kiện theo hash trước đó (compare-and-swap) để tránh ghi đè khi có yêu cầu đồng thời; đổi mật khẩu thành công sẽ thay session ID của trình duyệt hiện tại.
- **Kiểm thử bảo mật:** bổ sung `tests/auth_security_smoke.php`, hướng dẫn bảo mật cho contributor tại [AGENTS.md](AGENTS.md) và workflow [Vibe Check + account security audit](.github/workflows/vibe-security-audit.yml).
- **Phạm vi Vibe Check:** CI chỉ quét headers/CORS trên **PHP fixture nội bộ tại localhost**, không quét website production và không khẳng định toàn bộ lỗ hổng đã được loại bỏ.

Đọc chi tiết: [Kết quả audit](security-reports/SECURITY_AUDIT.md) · [Các sửa đổi](security-reports/SECURITY_FIXES.md) · [Bằng chứng kiểm thử](security-reports/SECURITY_TESTS.md) · [Rủi ro còn lại](security-reports/REMAINING_RISKS.md).

## Chức năng hiện có

| Khu vực | Nội dung |
| --- | --- |
| Trang chủ | Giới thiệu, khu tải game Android/Windows/iOS, thông tin cộng đồng và các liên kết website |
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
| `/quan-tri/tai-game` | Bật/tắt bản tải PC, Android, iOS; quản lý link và file upload (**admin**) |
| `/quan-tri/ma-gioi-thieu` | Mã giới thiệu (**admin/key**, theo quyền) |
| `/quan-tri/tai-khoan-key` | Tạo/khóa tài khoản role key (**admin**) |
| `/quan-tri/tai-khoan-game` | Quản lý tài khoản game (**admin**) |
| `/quan-tri/lich-su-thao-tac` | Lịch sử thao tác quản trị (**admin**) |
| `/quan-tri/diem-nong-dan`, `/quan-tri/diem-nong-dan/lich-su` | Quản lý và xem lịch sử điểm nông dân (**admin**) |
| `/quan-tri/nap` | Quản lý nạp (**admin**) |
| `/quan-tri/ty-le` | Tỷ lệ quy đổi và thưởng ngân hàng (**admin**) |
| `/quan-tri/cong-tai-nguyen` | Cộng tài nguyên game thủ công (**admin**) |
| `/quan-tri/cong-vnd` | Điều chỉnh ví VNĐ và/hoặc tổng nạp, có nhật ký (**admin**) |
| `/quan-tri/doi-soat` | Kiểm tra giao dịch (**admin**) |
| `/quan-tri/diem-tich-luy` | Quản lý Point và quà (**admin**) |

Route `/quan-tri/nap-game` đã được gỡ. URL cũ `/quan-tri/cong-tong-nap` hiện mở chung trang điều chỉnh `/quan-tri/cong-vnd` để giữ tương thích.

Định tuyến tập trung tại `config/routes.php` qua `.htaccess` và `index.php` ở thư mục gốc. Các alias cũ được xử lý theo cấu hình; API/callback không được tự ý redirect làm thay đổi phương thức HTTP. Xem [docs/ROUTING.md](docs/ROUTING.md).

## Quản lý tải game (Admin)

Mở **`/quan-tri/tai-game`** bằng tài khoản role `admin` để cấu hình ba nền tảng: **PC/Windows, Android (APK), iOS (IPA)**.

- Tích **Cho phép người chơi tải** để mở nút tải; bỏ tích sẽ đóng ngay cả đường dẫn tải file cũ.
- Chọn **Link tải bên ngoài** và dán URL HTTPS (GitHub Releases/CDN) hoặc chọn **File upload lên VPS** rồi chọn file tương ứng. Có thể đổi nguồn và giữ lại file đã tải lên khi tắt.
- Bấm **Lưu cấu hình tải game**. Trang chủ `/#tai-game` cập nhật tự động, không cần sửa SQL/PHP.
- File upload nằm tại `storage/game-downloads/` (đã chặn truy cập trực tiếp), được tải qua `/tai-game/tep?nen-tang=android|windows|ios` và hỗ trợ tải tiếp bằng HTTP Range. Chỉ admin được thay đổi.
- Metadata được lưu ở `storage/game-downloads/catalog.json`, không commit Git. Cấu hình `links.download_*` cũ chỉ làm mặc định **trước lần lưu đầu tiên** trong Admin.

**Giới hạn:** tối đa 512 MB/file ở tầng ứng dụng. Với XAMPP, cần tăng `upload_max_filesize`, `post_max_size` (lớn hơn kích thước file), `max_input_time`, `max_execution_time` và khởi động lại Apache. Cloudflare cũng có thể giới hạn kích thước upload tùy gói. File lớn hoặc lượng tải cao nên đặt ở GitHub Releases/CDN để không nghẽn VPS. IPA cần được ký và có cách cài phù hợp với iOS.

Chi tiết triển khai và cấu hình: [docs/GAME_DOWNLOADS.md](docs/GAME_DOWNLOADS.md).

## Luồng nạp tiền và Point

1. Người chơi tạo yêu cầu nạp thẻ hoặc đơn payOS.
2. Backend xác minh kết quả từ nhà cung cấp; **không coi việc mở QR hoặc redirect là đã thanh toán**.
3. Nạp thẻ thành công cộng số **VNĐ sau chiết khấu** vào `team_user.vnd`. Nạp ngân hàng cộng mệnh giá và **thưởng ngân hàng** theo cấu hình tại thời điểm tạo đơn.
4. `team_user.tongnap` ghi nhận **mệnh giá nạp gốc** (ví dụ nạp thẻ 10.000đ, thực nhận 8.000đ thì tổng nạp vẫn tăng 10.000đ), theo luồng ghi nhận giao dịch hợp lệ.
5. Người chơi dùng ví VNĐ để đổi mốc tài nguyên; Point được ghi nhận riêng và có thể đổi quà.

**Mặc định thưởng ngân hàng: +10%**, admin có thể chỉnh tại `/quan-tri/ty-le`. Cấu hình được lưu ở `storage/topup/bank-bonus.json`.

**Quà Điểm nông dân khi nạp bank:** admin có thể bật/tắt và chỉnh tỷ lệ tại `/quan-tri/ty-le` (mặc định **10.000đ nạp = 10.000 Điểm nông dân**; chức năng mặc định tắt cho tới khi admin bật). Các QR mới được ghi quyền nhận quà riêng theo mã đơn. Sau khi trả tiền và ví VNĐ đã cộng thành công, người chơi chọn nhân vật ở `/nap-ngan-hang` để nhận điểm **sau khi thoát game**. Đây là Điểm nông dân của nhân vật (`tob_char.pInfo[117]`), **không phải Point đổi quà của web**. Không cộng quà trùng khi webhook gửi lại. Xem [quy trình và xử lý sự cố](docs/BANK_FARMER_REWARDS.md).

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

**Điều chỉnh thủ công** tại `/quan-tri/cong-vnd` không phải giao dịch nạp thật, không tự tạo đơn payOS hay tích Point; admin chọn rõ cộng VNĐ, tổng nạp hoặc cả hai và ghi lý do. Nhật ký lưu tại `storage/topup/manual-wallet/`, cần sao lưu và đối soát nếu trạng thái chưa hoàn tất.

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
├── AGENTS.md                   # Quy tắc code an toàn cho contributor/AI
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
├── security-reports/           # Báo cáo audit, kiểm thử và rủi ro còn lại
├── storage/                    # Nhật ký và cấu hình riêng, cần bảo vệ
├── tests/                      # Smoke, HTTP regression, fixture database
├── tools/                      # Công cụ bảo trì chạy nội bộ
└── .github/workflows/          # CI kiểm thử tự động
```

## Kiểm thử và GitHub Actions

**Giữ nguyên bộ test trong repo** để tránh hồi quy khi sửa code. Hiện có 6 workflow:

| Workflow | Phạm vi |
| --- | --- |
| `php-js-syntax.yml` | Kiểm tra cú pháp PHP/JavaScript |
| `player-portal-checks.yml` | Tài khoản, nhân vật, bảng xếp hạng |
| `routing-regression.yml` | HTTP/route/session trên PHP 8.0, PHP 8.3 và Apache với database fixture |
| `security-checks.yml` | Phân quyền admin, CSRF, TOTP, file nhạy cảm |
| `topup-lint.yml` | Cú pháp và logic kiểm tra nạp |
| `vibe-security-audit.yml` | PHP 8.0 lint, kiểm thử account/admin/topup, chặn tracked artifact nhạy cảm, Vibe Check headers/CORS trên fixture localhost và lưu JSON report |

Chạy smoke test trên môi trường phát triển có PHP CLI:

```sh
for test in tests/*_smoke.php; do php "$test" || exit 1; done
```

Trên Windows CMD có thể chạy từng file, ví dụ:

```bat
C:\xampp\php\php.exe tests\topup_smoke.php
C:\xampp\php\php.exe tests\security_smoke.php
C:\xampp\php\php.exe tests\auth_security_smoke.php
C:\xampp\php\php.exe tests\responsive_smoke.php
```

**Integration test chỉ dùng database riêng `kpah_route_test`**, không chạy script `tests/seed_fixture.php` trên database production. Xem [docs/TESTING.md](docs/TESTING.md) để chạy đầy đủ.

File `.gitattributes` ép GitHub Linguist nhận diện PHP đúng và loại file Python trong `tests/` khỏi **biểu đồ tỷ lệ ngôn ngữ**, nhưng **không xóa Python hay tắt test**.

## Bảo mật và giới hạn

- Trang quản trị kiểm tra quyền ở backend, không chỉ ẩn menu. Role `key` bị chặn ở các trang admin không được cấp quyền.
- Dùng CSRF, giới hạn tần suất, session và kiểm tra quyền sở hữu dữ liệu; admin có hỗ trợ TOTP theo cấu hình.
- Các API tài khoản được gia cố kiểm tra trạng thái khóa, tránh ghi đè mật khẩu khi có cập nhật đồng thời; mật khẩu tài khoản game vẫn cần tương thích định dạng hash cũ.
- Một số bảng game/tài khoản sử dụng **MyISAM** nên không thể dựa vào database transaction để rollback các thay đổi số dư; cần giữ nguyên quy trình đối soát và nhật ký.
- Không công khai dump SQL, khóa thanh toán, file local hay `storage/`.
- Các bài test tự động **không thay thế** kiểm thử giao dịch thật, callback, Cloudflare, HTTPS, XAMPP và giao diện trực quan trên thiết bị thực tế.

**Giới hạn audit:** Các bài kiểm tra CI không thay thế kiểm thử luồng thanh toán, game server Java hoặc cấu hình VPS thực tế. Mã Java và SQL export được rà soát riêng, **không nằm trong bản cập nhật website**. Xem [rủi ro còn lại](security-reports/REMAINING_RISKS.md) trước khi triển khai những thay đổi liên quan đến dữ liệu tiền/game.

Tài liệu: [Bảo mật](docs/SECURITY_HARDENING.md) · [Triển khai URL](docs/ROUTING.md) · [Kiểm thử](docs/TESTING.md) · [Security Audit](security-reports/SECURITY_AUDIT.md).

---

**NGHIỆN KPAH** · [nghienkpah.io.vn](https://nghienkpah.io.vn) · [GitHub](https://github.com/thachdeptrai/web-kpah)
