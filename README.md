# NGHIỆN KPAH

Website dành cho cộng đồng người chơi KPAH: đăng ký, đăng nhập, quản lý tài khoản, xem nhân vật, bảng xếp hạng và khu nạp tài nguyên.

**Website:** [nghienkpah.io.vn](https://nghienkpah.io.vn)  
**Công nghệ:** PHP · MySQL/MariaDB · HTML/CSS/JavaScript  
**Giao diện:** Desktop và mobile

## Chức năng

| Khu vực | Chức năng |
| --- | --- |
| Trang chủ | Thông tin website, liên kết tài khoản, cộng đồng và tải game khi được cấu hình |
| Tài khoản | Đăng ký, đăng nhập, khôi phục mật khẩu qua email |
| Hồ sơ | Thông tin tài khoản, cập nhật liên hệ, đổi mật khẩu |
| Nhân vật | Xem nhân vật thuộc tài khoản đang đăng nhập, cấp độ, kinh nghiệm, Xu, Lượng và Lượng khóa |
| Xếp hạng | Top cấp độ và Top nạp Lượng từ dữ liệu game |
| Quản trị | Tổng quan, tài khoản quản trị và mã giới thiệu |
| Quản lý nạp | Tỷ lệ quy đổi, cộng tài nguyên thủ công, lịch sử và đối soát |
| Nạp thẻ | Đã có giao diện và mã tích hợp đối tác, **chưa bật nhận thẻ thật mặc định** |
| Nạp ngân hàng | Có trang riêng, **chưa triển khai thanh toán** |

### Đường dẫn

**Người chơi**

| Đường dẫn | Nội dung |
| --- | --- |
| `/` | Trang chủ |
| `/dang-ky` | Đăng ký |
| `/dang-nhap` | Đăng nhập |
| `/quen-mat-khau` | Lấy lại mật khẩu |
| `/tai-khoan` | Tổng quan và các nút chuyển trang |
| `/ho-so` | Hồ sơ, cập nhật liên hệ và đổi mật khẩu |
| `/nhan-vat` | Nhân vật của tài khoản đang đăng nhập |
| `/bang-xep-hang` | Chín bảng xếp hạng, Top 1–3 và 4–10 |
| `/nap` | Chọn phương thức nạp |
| `/nap-the` | Nạp thẻ cào |
| `/nap-ngan-hang` | Nạp ngân hàng (chưa mở) |
| `/lich-su-nap` | Lịch sử nạp của tài khoản |
| `/top-nap` | Top nạp Lượng |

URL được phân giải từ `config/routes.php` qua `.htaccess` và `index.php`. GET/HEAD của trang `.php` cũ chuyển 301 sang URL mới, giữ query string. POST cũ được xử lý trực tiếp; API và callback cũ không bị chuyển hướng.

**Quản trị**

| Đường dẫn | Nội dung |
| --- | --- |
| `/quan-tri/dang-nhap` | Đăng nhập quản trị |
| `/quan-tri` | Trang quản trị chính |
| `/quan-tri/ma-gioi-thieu` | Quản lý mã giới thiệu |
| `/quan-tri/nap` | Tổng quan quản lý nạp |
| `/quan-tri/ty-le` | Chỉnh tỷ lệ quy đổi |
| `/quan-tri/cong-tai-nguyen` | Cộng tài nguyên cho nhân vật |
| `/quan-tri/doi-soat` | Xem giao dịch, đối soát |
| `/quan-tri/nap-game` | Xem bảng nạp game hiện có (chỉ đọc) |

Các trang trong khu tài khoản và quản trị đều yêu cầu đăng nhập với quyền tương ứng.

## Cài đặt và cập nhật

### Yêu cầu

- PHP **8.0 trở lên** được khuyến nghị; mã nền có kiểm tra PHP >= 7.2.
- Apache 2.4 có `mod_rewrite`, `AllowOverride All`, website đặt ở DocumentRoot (ví dụ `C:\xampp\htdocs`). Không triển khai trong URL thư mục con nếu chưa bổ sung base path.
- MySQL/MariaDB với `pdo_mysql`.
- PHP có quyền ghi vào `storage/` để lưu dữ liệu giới hạn lượt thao tác và lịch sử nạp.
- Cấu hình SMTP nếu sử dụng các chức năng gửi mã xác minh email.
- HTTPS cho website hoạt động công khai.
- `curl` chỉ cần khi kết nối API đối tác nạp thẻ.

### Cập nhật trên VPS

Nếu website đã được triển khai từ GitHub, trong thư mục dự án chạy:

```bat
cd C:\xampp\htdocs
git pull --ff-only origin main
```

Không chạy lại file dump `kpah.sql` trên database đang hoạt động. Sao lưu dữ liệu và file cấu hình riêng trước khi nâng cấp.

### Cấu hình website

Sao chép mẫu:

```text
config/register.local.sample.php
                ↓
config/register.local.php
```

Điền kết nối database, SMTP và các đường dẫn cần dùng. File `config.register.local.php` được `.gitignore` loại trừ; **không commit mật khẩu hay khóa API**.

Có thể dùng biến môi trường cho database:

```text
KPAH_DB_HOST
KPAH_DB_PORT
KPAH_DB_NAME
KPAH_DB_USER
KPAH_DB_PASS
```

Cấu hình cũ `config.register.local.php` ở thư mục gốc vẫn được đọc nếu chưa tạo `config/register.local.php`; cấu hình mới được ưu tiên. Không cần chuyển file cấu hình trên VPS ngay.

Tham số mặc định nằm trong `config/register.php`. Bộ nạp cấu hình đặt tại `app/includes/config_loader.php` và được gọi qua `app/includes/bootstrap.php`.

> Repo website không thay thế code game server. Một số nghiệp vụ, đặc biệt là cấp tài nguyên sau khi thanh toán, cần được kiểm tra ở phía game server trước khi sử dụng thực tế.

## Database

Website đang dùng các bảng có sẵn trong database KPAH. Một số bảng quan trọng:

| Bảng | Mục đích |
| --- | --- |
| `team_user` | Tài khoản game |
| `tob_web_info_char` | Liên kết nhân vật với tài khoản trên web |
| `tob_char` | Nhân vật và số dư Xu/Lượng/Lượng khóa |
| `tob_char_luong` | Dữ liệu cấp độ/kinh nghiệm phục vụ hiển thị |
| `tob_event_top` | Thống kê Top nạp Lượng |
| `board_naptien`, `board_created` | Các bảng nạp có sẵn của game; chưa xác nhận đầy đủ cách server xử lý |
| `kpah_referral_codes`, `kpah_referral_code_uses` | Mã giới thiệu và lịch sử sử dụng |
| `kpah_web_admins` | Tài khoản quản trị website |

**Phân biệt hai ID tài khoản:** `team_user.id` là ID dùng để liên kết dữ liệu nhân vật trong luồng web hiện tại; `team_user.userid` là cột khác, không được dùng thay tùy ý.

Khu nạp mới **không yêu cầu thêm bảng hay thay đổi schema SQL**. Tuy nhiên, chức năng cộng tài nguyên thủ công của admin có thể cập nhật các cột số dư hiện có trong `tob_char` sau khi admin xác nhận.

Bộ mã giới thiệu có file `database/referral_codes.sql` dành cho môi trường chưa có các bảng quản trị/giới thiệu tương ứng. **Không import lại trên hệ thống đang chạy khi các bảng đã tồn tại và hoạt động bình thường.**

## Bảng xếp hạng

Trang `rankings.php` dùng truy vấn **chỉ đọc**, chọn hạng mục bằng danh sách cố định ở `app/includes/rankings.php`. Hiện có 9 hạng mục: Cấp độ, Danh vọng, Điểm PK, Bang hội, Nạp Lượng, Điểm sự kiện, Thách đấu, Chiếm thành và Sự kiện WC.

Giao diện hiển thị Top 1 ở trung tâm, Top 2 trái, Top 3 phải; Top 4–10 bên dưới. Nếu bảng chưa có dữ liệu, hiển thị trạng thái trống. Hình chiến binh/pháp sư/cung thủ là SVG minh họa, không phải sprite nhân vật thực tế.

Trang tài khoản `home.php` chỉ hiển thị tổng quan. Các form đã chuyển sang `profile.php` và danh sách nhân vật đã chuyển sang `characters.php`; API cũ giữ nguyên.

**Không yêu cầu thay đổi SQL.** Không hiển thị bảng/cột game, mật khẩu, số điện thoại hay số dư riêng tư trên bảng xếp hạng.

## Quản trị

Tài khoản quản trị web lưu tại `kpah_web_admins`, độc lập với tài khoản game `team_user`. Hệ thống kiểm tra quyền `admin` và trạng thái tài khoản trước khi vào các trang quản trị.

Các thao tác đang hỗ trợ:

- Tạo và quản lý mã giới thiệu.
- Xem thông tin tổng quan từ database.
- Điều chỉnh tỷ lệ quy đổi Xu, Lượng thường và Lượng khóa.
- Cộng tài nguyên thủ công cho đúng nhân vật, kèm xác nhận và nhật ký.
- Xem các giao dịch nạp và dữ liệu tại `board_naptien` / `board_created`.

Trước khi cộng tài nguyên thủ công, **nhân vật phải thoát game**. Bảng `tob_char` trong bản SQL đã kiểm tra dùng MyISAM, không có rollback giao dịch. Nếu thao tác chưa rõ kết quả, phải đối soát thay vì bấm cộng lần nữa.

## Nạp tài nguyên

Tỷ lệ mặc định cho mỗi **10.000 VNĐ**:

| Tài nguyên | Số nhận |
| --- | ---: |
| Lượng thường (không khóa) | 100 |
| Lượng khóa | 200 |
| Xu | Chưa bật quy đổi VNĐ mặc định |

Admin có thể sửa tỷ lệ tại `/quan-tri/ty-le`. Không bắt buộc người dùng chọn gói giá cố định; giao dịch thẻ phải nhập đúng mệnh giá của nhà phát hành.

Cấu hình tỷ lệ và nhật ký của module nạp được lưu trong vùng riêng tại `storage/topup/`, không thêm bảng SQL. Thư mục `storage/` có cấu hình chặn truy cập trực tiếp qua web.

**Trạng thái hiện tại**

- Có màn hình gửi thẻ, lịch sử và phần tích hợp API đối tác.
- **Chưa mở gửi thẻ thật mặc định.** Phải cấu hình đối tác và xác minh cơ chế game server tiêu thụ `board_naptien` trước khi kích hoạt.
- Nạp ngân hàng chưa kết nối, không có QR hay tài khoản ngân hàng để chuyển tiền.
- Một giao dịch ở trạng thái chờ cấp **không đồng nghĩa** tài nguyên đã vào game.
- Nạp thật, webhook và cập nhật số dư chưa được xác nhận hoạt động trọn vẹn trên môi trường production.

Chi tiết quy trình, biến môi trường, lưu ý về MyISAM và điều kiện bật nạp tại [`docs/TOPUP_TEAMWORK.md`](docs/TOPUP_TEAMWORK.md).

## Cấu trúc chính

| Thư mục/file | Trách nhiệm |
| --- | --- |
| `index.php` | Điểm vào HTTP duy nhất |
| `config/routes.php` | Danh sách URL, alias cũ và loại session |
| `config/register.php` | Cấu hình mặc định |
| `config/register.local.php` | Cấu hình riêng, không commit |
| `app/pages/` | Trang chủ và tài khoản người chơi |
| `app/admin/` | Đăng nhập admin, tổng quan, mã giới thiệu |
| `app/topup/` | Trang nạp và quản trị nạp |
| `app/api/` | Endpoint xử lý form, hồ sơ và email |
| `app/includes/` | Nghiệp vụ, database, session và bảo mật |
| `assets/` | CSS, JavaScript, font và hình ảnh công khai |
| `storage/` | Session phụ, giới hạn truy cập và nhật ký nạp |
| `database/` | SQL hỗ trợ quản trị, giới thiệu |
| `tools/` | Chẩn đoán nội bộ, bị chặn qua HTTP |
| `tests/` | Kiểm thử đơn vị, HTTP và schema mẫu |
| `docs/` | Hướng dẫn triển khai, bảo mật, kiểm thử |

## Bảo mật admin và database

Hướng dẫn bật xác thực hai bước (TOTP), giới hạn IP, bảo vệ backup SQL, phân quyền MySQL và kiểm tra VPS: [`docs/SECURITY.md`](docs/SECURITY.md).

Lưu ý: không để dump SQL, khóa API, file `.env` hay `storage/topup/` trong Git hoặc thư mục có thể tải công khai. Các lớp bảo vệ trong code không thay thế cập nhật Apache/PHP, quyền DB tối thiểu và Cloudflare.

## Kiểm tra trước khi chạy thật

Kiểm tra cú pháp PHP, ví dụ:

```bat
C:\xampp\php\php.exe -l app\pages\home.php
C:\xampp\php\php.exe -l app\topup\index.php
C:\xampp\php\php.exe -l app\topup\card.php
C:\xampp\php\php.exe -l app\topup\admin\grant.php
```

Nếu có PHP CLI, chạy bộ kiểm tra quy đổi:

```bat
C:\xampp\php\php.exe tests\topup_smoke.php
```

Sau khi cập nhật, kiểm tra giao diện PC/mobile, đăng nhập, đăng ký, OTP, quyền admin và link CSS/JS. Với giao diện đang được cache qua trình duyệt hoặc Cloudflare, thử tải lại mạnh hoặc xóa cache của file tương ứng.

**Không sử dụng giao dịch thẻ thật để thử hệ thống.** Luồng nhà cung cấp, xác minh callback, chống cấp trùng và server game phải được kiểm tra trên môi trường staging trước.

## Bảo mật quản trị và dữ liệu

Admin sử dụng lớp kiểm tra quyền dùng chung, giới hạn đăng nhập theo IP/tài khoản, CSRF và bảo vệ session. Có thể bật **xác thực hai bước TOTP** từ ứng dụng Authenticator bằng biến môi trường trên VPS, không cần thay đổi SQL.

Website chặn truy cập trực tiếp vào thư mục Git, file cấu hình, SQL dump, backup và dữ liệu nội bộ qua Apache `.htaccess`. Các truy vấn sử dụng PDO prepared statements; vẫn cần kiểm thử thực tế và **giới hạn quyền tài khoản MariaDB**, tránh dùng `root` cho website.

Xem [hướng dẫn bảo mật và cách bật 2FA](docs/SECURITY_HARDENING.md) để cấu hình Windows Server/XAMPP, Cloudflare và kiểm tra các URL nhạy cảm phải trả 403/404.

> Những lớp bảo vệ trong code không thể thay thế firewall, quyền SQL tối thiểu, cập nhật máy chủ và kiểm thử xâm nhập.

## Theo dõi phát triển

Công việc khu nạp được chia thành bốn nhóm trên GitHub:

- [Backend và tích hợp database game](https://github.com/thachdeptrai/web-kpah/issues/2)
- [Frontend và responsive](https://github.com/thachdeptrai/web-kpah/issues/3)
- [Quản trị nạp](https://github.com/thachdeptrai/web-kpah/issues/4)
- [API đối tác và bảo mật giao dịch](https://github.com/thachdeptrai/web-kpah/issues/5)

Các issue là nơi theo dõi tiến độ và review; không đồng nghĩa có agent hay thành viên khác đang tự động thực hiện.

---

**NGHIỆN KPAH** · [Website](https://nghienkpah.io.vn) · [Mã nguồn](https://github.com/thachdeptrai/web-kpah)

## URL và kiểm thử hồi quy

Xem [hướng dẫn chuyển cấu trúc và triển khai](docs/ROUTING.md), [phạm vi kiểm thử](docs/TESTING.md). Thay đổi này không chạy migration hoặc sửa dữ liệu game.
