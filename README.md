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
| `/profile.php` | Hồ sơ, cập nhật liên hệ và đổi mật khẩu |
| `/characters.php` | Nhân vật của tài khoản đang đăng nhập |
| `/rankings.php` | Chín bảng xếp hạng, Top 1–3 và 4–10 |
| `/nap` | Chọn phương thức nạp |
| `/nap-the` | Nạp thẻ cào |
| `/nap-ngan-hang` | Nạp ngân hàng (chưa mở) |
| `/topup/history.php` | Lịch sử nạp của tài khoản |
| `/topup/top.php` | Top nạp Lượng |

Các đường dẫn ngắn sử dụng `.htaccess`. Trang nạp chính được tổ chức trong `topup/`; các đường dẫn nạp cũ chuyển hướng về đó.

**Quản trị**

| Đường dẫn | Nội dung |
| --- | --- |
| `/admin/login.php` | Đăng nhập quản trị |
| `/admin/index.php` | Trang quản trị chính |
| `/admin/referrals.php` | Quản lý mã giới thiệu |
| `/topup/admin/index.php` | Tổng quan quản lý nạp |
| `/topup/admin/rates.php` | Chỉnh tỷ lệ quy đổi |
| `/topup/admin/grant.php` | Cộng tài nguyên cho nhân vật |
| `/topup/admin/orders.php` | Xem giao dịch, đối soát |
| `/topup/admin/legacy.php` | Xem bảng nạp game hiện có (chỉ đọc) |

Các trang trong khu tài khoản và quản trị đều yêu cầu đăng nhập với quyền tương ứng.

## Cài đặt và cập nhật

### Yêu cầu

- PHP **8.0 trở lên** được khuyến nghị; mã nền có kiểm tra PHP >= 7.2.
- Apache có `mod_rewrite` và hỗ trợ `.htaccess`.
- MySQL/MariaDB với `pdo_mysql`.
- PHP có quyền ghi vào `storage/` để lưu dữ liệu giới hạn lượt thao tác và lịch sử nạp.
- Cấu hình SMTP nếu sử dụng các chức năng gửi mã xác minh email.
- HTTPS cho website hoạt động công khai.
- `curl` chỉ cần khi kết nối API đối tác nạp thẻ.

### Triển khai webroot riêng trên VPS

**Bản tái cấu trúc phải chuyển Apache DocumentRoot sang `C:/xampp/htdocs/public`.** Không chỉ `git pull` rồi để Apache trỏ vào root cũ, vì mã PHP đã di chuyển khỏi đó. Hãy làm theo [hướng dẫn chuyển đổi và rollback](docs/PRIVATE_WEBROOT.md); thử trên nhánh `refactor/private-webroot` trước khi thay đổi trang thật.

**Không import lại SQL, không xóa dữ liệu game hoặc `storage/topup`.**

### Cấu hình website

Sao chép mẫu:

```text
app/config/config.register.local.sample.php
                ↓
app/config/config.register.local.php
```

Điền kết nối database, SMTP và các đường dẫn cần dùng. File local config được `.gitignore` loại trừ (trong `app/config` hoặc tại root cũ khi nâng cấp); **không commit mật khẩu hay khóa API**.

Có thể dùng biến môi trường cho database:

```text
KPAH_DB_HOST
KPAH_DB_PORT
KPAH_DB_NAME
KPAH_DB_USER
KPAH_DB_PASS
```

Tham số mặc định nằm trong `app/config/config.register.php`. Bộ nạp cấu hình nằm ở `app/includes/config_loader.php` và được gọi qua `app/includes/bootstrap.php`.

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

Admin có thể sửa tỷ lệ tại `/topup/admin/rates.php`. Không bắt buộc người dùng chọn gói giá cố định; giao dịch thẻ phải nhập đúng mệnh giá của nhà phát hành.

Cấu hình tỷ lệ và nhật ký của module nạp được lưu trong vùng riêng tại `storage/topup/`, không thêm bảng SQL. Thư mục `storage/` có cấu hình chặn truy cập trực tiếp qua web.

**Trạng thái hiện tại**

- Có màn hình gửi thẻ, lịch sử và phần tích hợp API đối tác.
- **Chưa mở gửi thẻ thật mặc định.** Phải cấu hình đối tác và xác minh cơ chế game server tiêu thụ `board_naptien` trước khi kích hoạt.
- Nạp ngân hàng chưa kết nối, không có QR hay tài khoản ngân hàng để chuyển tiền.
- Một giao dịch ở trạng thái chờ cấp **không đồng nghĩa** tài nguyên đã vào game.
- Nạp thật, webhook và cập nhật số dư chưa được xác nhận hoạt động trọn vẹn trên môi trường production.

Chi tiết quy trình, biến môi trường, lưu ý về MyISAM và điều kiện bật nạp tại [`docs/TOPUP_TEAMWORK.md`](docs/TOPUP_TEAMWORK.md).

## Cấu trúc chính

```text
web-kpah/
├── app/                         MÃ NGUỒN PHP RIÊNG TƯ
│   ├── config/                  Cấu hình server + mẫu local
│   ├── includes/                Database, bảo mật, mail và xử lý nghiệp vụ
│   └── pages/                   Logic trang user, admin, API, nạp thẻ
├── public/                      APACHE DOCUMENTROOT
│   ├── .htaccess                URL thân thiện và chặn file nhạy cảm
│   ├── assets/                  CSS, JavaScript, SVG và font
│   ├── admin/, api/, topup/     PHP entrypoints nhỏ giữ URL cũ
│   └── index.php, login.php...  PHP entrypoints nhỏ
├── storage/                     Giới hạn lượt + lịch sử nạp riêng
├── database/                    SQL hỗ trợ, không public
├── docs/                        Hướng dẫn cài đặt và bảo mật
├── tests/                       Kiểm tra hồi quy
├── tools/                       Công cụ chẩn đoán ngoài webroot
└── .github/                     CI
```

Mọi URL web cũ vẫn được giữ. PHP xử lý thật nằm ngoài `public/`. Cấu hình Apache và quy trình chuyển đổi: [`docs/PRIVATE_WEBROOT.md`](docs/PRIVATE_WEBROOT.md).

**Lưu ý:** GitHub repo đang Public. Nếu cần giấu mã nguồn khỏi người xem GitHub, phải đổi repository visibility thành **Private** sau khi bảo đảm VPS có quyền `git pull`. Việc đổi DocumentRoot chỉ bảo vệ mã nguồn trên server; HTML/CSS/JS phía trình duyệt vẫn có thể xem.

## Bảo mật admin và database

Hướng dẫn bật xác thực hai bước (TOTP), giới hạn IP, bảo vệ backup SQL, phân quyền MySQL và kiểm tra VPS: [`docs/SECURITY.md`](docs/SECURITY.md).

Lưu ý: không để dump SQL, khóa API, file `.env` hay `storage/topup/` trong Git hoặc thư mục có thể tải công khai. Các lớp bảo vệ trong code không thay thế cập nhật Apache/PHP, quyền DB tối thiểu và Cloudflare.

## Kiểm tra trước khi chạy thật

Kiểm tra cú pháp PHP, ví dụ:

```bat
C:\xampp\php\php.exe -l home.php
C:\xampp\php\php.exe -l topup\index.php
C:\xampp\php\php.exe -l topup\card.php
C:\xampp\php\php.exe -l topup\admin\grant.php
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
