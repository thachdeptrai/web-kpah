# Cấu trúc và URL trên XAMPP

`index.php` **vẫn nằm ở thư mục gốc**. Apache/XAMPP vẫn dùng file này làm DirectoryIndex. Nội dung trang chủ nằm tại `app/pages/index.php`; `index.php` nạp router rồi trang tương ứng. Không cần Node, Composer hay đổi DocumentRoot.

## Yêu cầu triển khai

- Website đặt tại gốc domain, ví dụ `C:\xampp\htdocs`. Các liên kết nội bộ dùng đường dẫn bắt đầu bằng `/`.
- Apache 2.4, `mod_rewrite`, `AllowOverride All`. Giữ `.htaccess` cùng cấp `index.php`.
- PHP 8.0+ và các extension hiện có của dự án.
- Không import `tests/fixtures/schema.sql` hoặc `kpah.sql` vào database thật. Refactor này không có migration.

## Cách phân giải URL

`config/routes.php` định nghĩa mọi route và handler bằng danh sách cố định. URL không được ghép thành đường dẫn include. `app/`, `config/`, `tools/`, `storage/`, SQL và tài liệu nội bộ bị chặn truy cập trực tiếp.

| Luồng | Hành vi |
| --- | --- |
| GET/HEAD `/home.php?x=1` | 301 đến `/tai-khoan?x=1` |
| GET `/profile.php#security` | 301 đến `/ho-so`; trình duyệt giữ fragment theo cơ chế redirect |
| POST `/login.php` | Chạy handler đăng nhập trực tiếp, giữ body và CSRF |
| POST `/api/register.php` | API cũ vẫn hoạt động, không redirect |
| POST `/topup/card-callback.php` | Callback cũ vẫn hoạt động, không redirect |
| GET `/dang-nhap/` | 301 đến `/dang-nhap` |
| URL không có trong danh sách | HTTP 404 |
| URL chứa CR/LF hoặc dấu phân cách được mã hóa | Bị từ chối |

GET/HEAD trang cũ chuyển sang URL đẹp. Nếu một form cũ POST thất bại, trình duyệt có thể tạm giữ URL cũ để không làm mất nội dung gửi. Form/menu của phiên bản mới đều dùng URL đẹp.

Session quản trị được chọn bằng `KP_ROUTE_SCOPE` từ route đã xác thực, trước khi khởi tạo session. Không dựa vào query string hoặc đường dẫn `/index.php`. Admin và người chơi giữ cookie riêng, đăng xuất bên này không xóa bên kia.

## Cấu hình cũ và dữ liệu vận hành

Ưu tiên `config/register.local.php`. Nếu file này chưa có, vẫn đọc `config.register.local.php` ở thư mục gốc. Biến môi trường database tiếp tục có độ ưu tiên cao nhất như trước. Không cần đổi file cấu hình riêng trên VPS đang chạy.

`storage/` giữ nguyên vị trí để không mất tỷ lệ nạp, lịch sử, đối soát hay dữ liệu giới hạn truy cập. File SQL người chơi không được đưa vào commit.

## Cập nhật

1. Review và merge PR đã kiểm thử. Nếu có tool auto-pull trên VPS, việc merge vào `main` có thể lập tức cập nhật web; thực hiện vào thời điểm phù hợp.
2. Tại repo VPS: `git pull --ff-only origin main`.
3. Kiểm tra `/`, `/dang-nhap`, `/tai-khoan`, `/quan-tri/dang-nhap`, và URL cũ `/home.php`.
4. Kiểm tra `/app/pages/index.php`, `/config/register.php`, `/storage/` trả 403.
5. Nếu OPcache không kiểm tra timestamp, restart Apache để nạp mã mới.

Triển khai bằng copy ZIP phải xóa các file PHP cũ đã di chuyển theo diff; dùng Git sẽ áp dụng đúng danh sách thêm/xóa. Không chép đè `.htaccess` bằng snippet cũ.

Chỉ bỏ đuôi `.php` không phải biện pháp chống SQL injection. Chặn mã nguồn nội bộ, kiểm tra quyền, CSRF và truy vấn tham số hóa vẫn là các lớp bảo vệ chính.
