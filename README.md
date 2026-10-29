# NGHIỆN KPAH

[![PHP & JS syntax](https://github.com/thachdeptrai/web-kpah/actions/workflows/php-js-syntax.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/php-js-syntax.yml)
[![Security regression](https://github.com/thachdeptrai/web-kpah/actions/workflows/security-checks.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/security-checks.yml)
[![Vibe Check](https://github.com/thachdeptrai/web-kpah/actions/workflows/vibe-security-audit.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/vibe-security-audit.yml)
[![Semgrep security](https://github.com/thachdeptrai/web-kpah/actions/workflows/semgrep-security.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/semgrep-security.yml)
[![Public JavaScript build](https://github.com/thachdeptrai/web-kpah/actions/workflows/build-public-js.yml/badge.svg?branch=main)](https://github.com/thachdeptrai/web-kpah/actions/workflows/build-public-js.yml)

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

## JavaScript production và API (19/10/2026)

- Website tự phục vụ bản **JS minify bằng Terser** tại URL `/assets/js/*.js` khi có bản build tương ứng trong `assets/js-dist/`. GitHub Actions sinh **23 file build**, kiểm tra cú pháp và test JS trước khi commit lên `main`; không cần Node/npm trên XAMPP khi pull bản build. Có fallback sang file gốc nếu bản build chưa được phát hành. **Đây không phải mã hóa JS**: trình duyệt vẫn phải nhận được mã và repository vẫn có source công khai.
- Các API phi HTML có chính sách `private/no-store`, `Vary: Cookie`, `nosniff`, CORP same-origin, CSP hạn chế nội dung và noindex. Dữ liệu API truyền qua HTTPS/TLS; DevTools vẫn xem được JSON của chính phiên người dùng. Không đặt khóa AES bên trong JS hoặc tự thay đổi payload của callback thanh toán.
- Xem [tài liệu JS và API](docs/BROWSER_JS_API_SECURITY.md) và [kiểm thử browser/API](tests/client_api_transport_security_smoke.php). Các cơ chế phân quyền, CSRF, chữ ký thanh toán và kiểm tra phía server vẫn là biện pháp chính.

## Rà soát DevTools và quyền truy cập — 19/10/2026

- **Công khai hợp lệ:** trình duyệt luôn xem được HTML/CSS/JS, ảnh và request/response của chính phiên. Không coi obfuscation, che link JS hoặc chặn DevTools là bảo mật.
- **API nạp:** `/api/payos-status` và `/api/card-fees` từ chối phiên hết hạn, tài khoản bị khóa hoặc không còn tồn tại; trả lỗi mà không lộ thông tin DB.
- **Đăng xuất chống CSRF:** GET tại `/api/logout` và `/quan-tri/dang-xuat` chỉ mở trang xác nhận; POST có CSRF mới kết thúc phiên. Đường dẫn cũ được giữ nguyên.
- **Test thực tế:** bộ HTTP regression với hai tài khoản người chơi fixture và một Admin, thử URL nhân vật người khác, truy cập trực tiếp khu quản trị và POST trái quyền. PHP 8.0/8.3 + Apache chạy với MariaDB fixture; không thực hiện trên production.
- **Giới hạn:** chưa có kiểm thử đồng thời/replay thanh toán và cấp quà trên staging đầy đủ; xem [ma trận dữ liệu DevTools và kết quả audit](security-reports/DEVTOOLS_AUDIT_2026-10-19.md).

## Cập nhật bảo mật — 18/10/2026

Tiếp tục audit `main` bằng cách truy vết source và chạy GitHub Actions. Các phát hiện có bằng chứng được vá theo nhóm; **không thử khai thác production, không thay đổi số dư, schema game, dữ liệu người chơi hay serialization item**.

- **Chống dò mật khẩu:** đăng nhập áp dụng ba ngân sách riêng: **80 lần/15 phút theo IP**, **20 lần/15 phút theo tên tài khoản** và **8 lần/15 phút theo cặp IP–tên tài khoản**. Nếu hệ thống lưu giới hạn gặp lỗi, yêu cầu không được bỏ qua kiểm tra.
- **Liên kết an toàn:** `kp_safe_url()` kiểm tra URL chứa dấu gạch chéo ngược, ký tự điều khiển và khoảng trắng không an toàn; đã có regression test cho các URL hợp lệ và không hợp lệ.
- **Ổn định Admin:** sửa fixture test thiếu logger và lỗi thanh điều hướng bị tràn ở màn hình **320px**. Browser regression đã có lần chạy **PASS**.
- **Semgrep:** [`semgrep-security.yml`](.github/workflows/semgrep-security.yml) sử dụng Semgrep **1.181.0**. Bộ **2 quy tắc có mức chặn** chạy trên **96 file PHP**, ghi nhận **0 finding trong phạm vi hai quy tắc**. Bộ `p/php` rộng hơn chạy **23 quy tắc**, ghi nhận **130 cảnh báo cần rà soát** (116 output, 5 SQL, 5 filename, 4 callable), **không phải 130 lỗ hổng được xác minh**. Các vị trí SQL và đường dẫn file bị cảnh báo đã được đối chiếu với whitelist/prepared statements; các cảnh báo output chưa được xác minh hết.
- **Kiểm thử và chính sách:** bổ sung `SECURITY.md`, checklist `AGENTS.md`, test offline và artifact kết quả scan. Các workflow PHP/JS, security regression, route/browser regression và Vibe Check fixture đã có lần chạy **PASS**. Theo dõi trạng thái mới nhất tại [GitHub Actions](https://github.com/thachdeptrai/web-kpah/actions).

**Rủi ro chưa kiểm thử tích hợp:** callback nạp tiền bị gửi lại, xử lý trùng khi cộng ví/Point/giftcode, cấp item trên Java game server và khôi phục khi ghi vào bảng MyISAM bị gián đoạn. **Strix, OWASP ZAP, Nuclei chưa được chạy** do chưa có staging/tài khoản thử được ủy quyền. **Vibe Check chỉ quét fixture localhost**, không quét production.

Xem [audit 18/10](security-reports/AUDIT_2026-10-18.md), [audit 15/10](security-reports/SECURITY_AUDIT.md), [chính sách bảo mật](SECURITY.md) và [rủi ro còn lại](security-reports/REMAINING_RISKS.md).

## Chức năng hiện có

| Khu vực | Nội dung |
| --- | --- |
| Trang chủ | Giới thiệu, tải game, các giftcode được admin công khai, thông tin cộng đồng và liên kết website |
| Tài khoản | Đăng ký, đăng nhập, khôi phục mật khẩu, xác minh email và cập nhật hồ sơ |
| Tổng quan | Ví VNĐ, tổng nạp, Point, nhân vật thuộc tài khoản, giao dịch gần đây, thông tin bảo mật và thành tích khi có dữ liệu |
| Nhân vật | Cấp độ, tài nguyên, bang hội, hồ sơ nâng cao và xem trang bị/hành trang/rương/túi đồ từ dữ liệu game |
| Xếp hạng | Các danh mục Top nạp, tài sản, cấp độ, VIP, chiến trường, nông trại…; chỉ hiện mục có dữ liệu phù hợp |
| Nạp thẻ | Tích hợp API đối tác thẻ; thẻ hợp lệ cộng **VNĐ thực nhận sau chiết khấu** vào ví |
| Nạp ngân hàng | payOS/VietQR; cộng ví VNĐ khi xác minh giao dịch, thưởng ngân hàng theo cấu hình |
| Ví VNĐ | Đổi mốc Xu/Lượng/Lượng khóa cho nhân vật đủ điều kiện; cần xác nhận nhân vật đã thoát game |
| Point | Tích điểm từ giao dịch nạp hợp lệ, xem số dư và gửi yêu cầu đổi quà |
| Quản trị | Điều hướng chung, CRUD giftcode và lịch sử nhận quà, quản lý nạp/đối soát, tỷ lệ quy đổi, thưởng ngân hàng, Point và quà, mã giới thiệu |
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
| `/nhan-vat/chi-tiet?name=...` | Xem trang bị và túi đồ của nhân vật thuộc tài khoản |
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
| `/quan-tri/giftcode` | Quản lý giftcode, công khai lên trang chủ và tra lịch sử nhân vật đã nhận (**admin**) |
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

Tài liệu giftcode: [Quản trị và hiển thị mã quà tặng](docs/GIFTCODES.md).

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

## Trung tâm người chơi và Toast thông báo

Đã có 4 đường dẫn (tất cả yêu cầu đăng nhập): `/giftcode-cua-toi`, `/thong-bao`, `/theo-doi-don-nap`, `/lich-su-bao-mat`.

**Quan trọng:** Sau khi pull mã mới, sao lưu DB và chạy `database/user_center.sql` để tạo/kiểm tra ba bảng thông báo và lịch sử bảo mật trong **`kpah_points`**. Script giữ nguyên dữ liệu đang có; không sửa cấu trúc game. Lịch sử bảo mật bắt đầu ghi từ thời điểm tính năng được kích hoạt; thông báo hiện ghi nhận đổi mật khẩu thành công, không giả lập thông báo thanh toán cho đơn cũ.

Các thao tác đã tích hợp hiển thị Toast góc phải gồm tiêu đề thành công/thất bại và lý do. Chi tiết cách mở rộng: [docs/USER_CENTER.md](docs/USER_CENTER.md).

## Thư mục RES và icon vật phẩm

Thư mục `res/` đã được tạo ở root, bên trong có `res/.gitignore` để **không commit tài nguyên copy từ game**. Copy **nội dung của `map/`** từ server Java sang `res/`; icon sẽ ở `res/icon/176.png` tương ứng `map/icon/176.png`.

Hồ sơ nhân vật tại `/nhan-vat` dẫn tới trang chi tiết `/nhan-vat/chi-tiet?name=...`, hiển thị trang bị/hành trang/rương/túi đồ bằng cách đọc các trường `tob_char.equip`, `inven`, `bag`, `tuido`. Tên từ `data_item.name`, icon từ `data_item.xstart` → `/res/icon/{xstart}.png`. Chỉ đọc dữ liệu của tài khoản đang đăng nhập.

Cách copy đúng, mẫu lệnh Windows, cấu trúc item Java và các giới hạn: [docs/CHARACTER_RES.md](docs/CHARACTER_RES.md).

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
├── SECURITY.md                 # Chính sách công bố lỗ hổng và vận hành an toàn
├── .semgrep/                   # Quy tắc phân tích PHP mức chặn
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

**Giữ nguyên bộ test trong repo** để tránh hồi quy khi sửa code. Hiện có **8 workflow**:

| Workflow | Phạm vi |
| --- | --- |
| `php-js-syntax.yml` | Kiểm tra cú pháp PHP/JavaScript |
| `player-portal-checks.yml` | Tài khoản, nhân vật, bảng xếp hạng |
| `routing-regression.yml` | HTTP/route/session trên PHP 8.0, PHP 8.3 và Apache với database fixture |
| `security-checks.yml` | Phân quyền admin, CSRF, TOTP, file nhạy cảm |
| `topup-lint.yml` | Cú pháp và logic kiểm tra nạp |
| `vibe-security-audit.yml` | PHP 8.0 lint, kiểm thử account/admin/topup, chặn tracked artifact nhạy cảm, Vibe Check headers/CORS trên fixture localhost và lưu JSON report |
| `semgrep-security.yml` | Semgrep 1.181.0: 2 quy tắc PHP chặn lỗi; quét thêm `p/php` ở chế độ cảnh báo và lưu artifact JSON |
| `build-public-js.yml` | Biên dịch JS đã minify (Terser), chạy syntax/JS smoke trước khi publish lên `main`; không sinh source map |

Chạy smoke test trên môi trường phát triển có PHP CLI:

```sh
for test in tests/*_smoke.php; do php "$test" || exit 1; done
```

Trên Windows CMD có thể chạy từng file, ví dụ:

```bat
C:\xampp\php\php.exe tests\topup_smoke.php
C:\xampp\php\php.exe tests\security_smoke.php
C:\xampp\php\php.exe tests\auth_security_smoke.php
C:\xampp\php\php.exe tests\security_auth_url_smoke.php
C:\xampp\php\php.exe tests\safe_url_security_smoke.php
C:\xampp\php\php.exe tests\admin_csrf_smoke.php
C:\xampp\php\php.exe tests\responsive_smoke.php
```

**Integration test chỉ dùng database riêng `kpah_route_test`**, không chạy script `tests/seed_fixture.php` trên database production. Xem [docs/TESTING.md](docs/TESTING.md) để chạy đầy đủ.

File `.gitattributes` ép GitHub Linguist nhận diện PHP đúng và loại file Python trong `tests/` khỏi **biểu đồ tỷ lệ ngôn ngữ**, nhưng **không xóa Python hay tắt test**.

## Bảo mật và giới hạn

- Trang quản trị kiểm tra quyền ở backend, không chỉ ẩn menu. Role `key` bị chặn ở các trang admin không được cấp quyền.
- Dùng CSRF, giới hạn tần suất, session và kiểm tra quyền sở hữu dữ liệu; admin có hỗ trợ TOTP theo cấu hình. Đăng nhập người chơi được giới hạn theo IP, tên tài khoản và cặp IP–tài khoản.
- Các API tài khoản được gia cố kiểm tra trạng thái khóa, tránh ghi đè mật khẩu khi có cập nhật đồng thời; mật khẩu tài khoản game vẫn cần tương thích định dạng hash cũ.
- Một số bảng game/tài khoản sử dụng **MyISAM** nên không thể dựa vào database transaction để rollback các thay đổi số dư; cần giữ nguyên quy trình đối soát và nhật ký.
- Không công khai dump SQL, khóa thanh toán, file local hay `storage/`.
- Semgrep chỉ chặn các mẫu theo quy tắc tập trung; kết quả scan sạch **không** đồng nghĩa website không còn lỗ hổng.
- Các bài test tự động **không thay thế** kiểm thử giao dịch thật, callback, Cloudflare, HTTPS, XAMPP và giao diện trực quan trên thiết bị thực tế.

**Giới hạn audit:** Các bài kiểm tra CI không thay thế kiểm thử luồng thanh toán, game server Java hoặc cấu hình VPS thực tế. Mã Java và SQL export được rà soát riêng, **không nằm trong bản cập nhật website**. Xem [rủi ro còn lại](security-reports/REMAINING_RISKS.md) trước khi triển khai những thay đổi liên quan đến dữ liệu tiền/game.

Tài liệu: [Chính sách bảo mật](SECURITY.md) · [Bảo mật triển khai](docs/SECURITY_HARDENING.md) · [Triển khai URL](docs/ROUTING.md) · [Kiểm thử](docs/TESTING.md) · [Audit 18/10](security-reports/AUDIT_2026-10-18.md) · [Audit 15/10](security-reports/SECURITY_AUDIT.md).

---

**NGHIỆN KPAH** · [nghienkpah.io.vn](https://nghienkpah.io.vn) · [GitHub](https://github.com/thachdeptrai/web-kpah)


## Database Point riêng (tùy chọn)

Có thể dùng schema MySQL/MariaDB thứ hai `kpah_points` cho `kp_points_*` trên cùng server database game. Import `database/points_separate.sql`, và **nếu đã có Point thật phải chuyển đủ dữ liệu trước khi bật cấu hình**. Hướng dẫn tại [docs/POINTS_SEPARATE_DATABASE.md](docs/POINTS_SEPARATE_DATABASE.md).


## Diễn đàn Báo lỗi & Góp ý

Có trang công khai `/dien-dan` và trang Admin `/quan-tri/forum`. **Import** `database/forum_feedback.sql` vào **database `kpah_points`** trước khi sử dụng; tên tài khoản trên forum được che, Admin có quyền phản hồi và đổi trạng thái. Hướng dẫn tại [docs/FORUM_FEEDBACK.md](docs/FORUM_FEEDBACK.md).
