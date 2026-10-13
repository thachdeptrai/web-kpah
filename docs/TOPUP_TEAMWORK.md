# KPAH — module nạp tài nguyên (không thay đổi schema SQL)

## Trạng thái thực hiện

- **Đã có giao diện:** `/nap` (chọn chức năng), `/nap-the` (thẻ cào), `/lich-su-nap`, `/top-nap` (top nạp từ `tob_event_top`), `/nap-ngan-hang` (chưa kích hoạt).
- **Đã có admin tách trang:** `/quan-tri/nap`, `rates.php`, `grant.php`, `orders.php`. Trang admin cũ chỉ có thêm nút dẫn đến khu quản lý nạp.
- **Đã có backend:** tạo yêu cầu thẻ, gửi API thẻ theo phương thức `charging`, nhận callback có kiểm tra chữ ký, xác minh lại qua API `check`, lưu idempotency/hồ sơ giao dịch ở `storage/topup`.
- **CHƯA LIVE nạp thẻ tự động:** mặc định khóa nút gửi thẻ thật. Phải có API đối tác và phải xác minh game server sử dụng `board_naptien` chính xác trước khi bật.
- **Ngân hàng:** chưa triển khai, không hiển thị thông tin nhận tiền.
- **Không cần import SQL.** Không tạo bảng, không đổi index/kiểu cột, không xóa/ALTER dữ liệu game. Các thao tác cộng thủ công được admin thực hiện trên cột nhân vật **hiện có** sau khi admin bấm xác nhận.

## Bảng sử dụng từ SQL gốc

| Mục đích | Bảng/cột |
|---|---|
| Kiểm tra phiên game | `team_user.id`, `team_user.username`, `team_user.ban` |
| Cộng trực tiếp bởi admin | `tob_char.userId`, `tob_char.id`, `tob_char.gold`, `tob_char.luong`, `tob_char.luonglock` |
| Hàng chờ **có điều kiện** cho giao dịch thẻ đã xác nhận | `board_naptien(username, svID, xu, luong, luongKhoa, ve)` |
| Top Lượng đã nạp | `tob_event_top.charname`, `tob_event_top.luongnap` |

**Lưu ý:** `board_naptien`, `board_created`, `tob_char` là MyISAM. Không có transaction rollback. Chưa có code server game ở repo web để xác minh server tiêu thụ `board_naptien` theo username và svID nào. Chưa bật tự cấp khi chưa xác minh.

## Giao diện và phân quyền

Trang user `/topup/*` yêu cầu session `kp_user_id`, tự timeout 30 phút. Admin `/topup/admin/*` yêu cầu session `kp_admin_id` role admin **và** đọc `kpah_web_admins` kiểm tra active. Có CSRF với POST trình duyệt; callback không dùng CSRF vì phải xác minh đối tác.

Các nút điều hướng dùng link sang trang riêng; không dồn màn hình nạp vào trang tài khoản hoặc admin.

## Cấu hình tỷ lệ không cần SQL

Mặc định cho mỗi 10.000đ:

- `luong` = 100 Lượng thường (không khóa).
- `luonglock` = 200 Lượng khóa.
- `gold` = 0 Xu (không bật quy đổi theo VNĐ; admin vẫn có thể cộng trực tiếp).

Admin cập nhật tỷ lệ tại `/quan-tri/ty-le`. Giá trị lưu trong `storage/topup/rates.json`, tự tạo sau khi admin lưu. Đảm bảo Apache/PHP có quyền ghi `storage/topup`. `storage/.htaccess` có sẵn để chặn truy cập tệp lưu lịch sử và cấu hình.

Có thể nhập VNĐ **bất kỳ** khi quy đổi; phần lẻ chưa đủ 1 đơn vị tài nguyên được làm tròn xuống.

## Bước cần làm trước khi nạp thẻ thật

1. **Xác minh game server:** tìm chỗ SELECT/INSERT/UPDATE/DELETE `board_naptien` trong code server thật. Kiểm tra ý nghĩa `svID`, game xử lý lượng khóa thế nào, có retry trùng không. Không đoán chỉ từ dump SQL.
2. **Thử staging:** sao lưu DB, tạo thẻ thử qua đối tác sandbox, xác minh chỉ xử lý một lần khi callback lặp lại và khi mạng mất.
3. **Đăng ký đối tác nạp thẻ:** mã đang dùng giao thức `chargingws/v2` của **napthesieure.vn** với dấu ký MD5 theo tài liệu của đối tác. Đây là lựa chọn adapter đầu tiên, không phải liên kết thương mại được bảo đảm. Cần xác minh trực tiếp tài liệu và độ tin cậy của đối tác trước khi dùng thẻ thật.
4. **Không commit khóa API.** Set môi trường PHP/Apache:
   - `KPAH_CARD_PARTNER_ID`: partner ID được cấp;
   - `KPAH_CARD_PARTNER_KEY`: partner key bí mật;
   - `KPAH_TOPUP_SIGNING_SECRET`: chuỗi ngẫu nhiên ít nhất 32 ký tự dùng để hash mã thẻ nội bộ;
   - `KPAH_TOPUP_GAME_QUEUE_VERIFIED=1`: **chỉ bật khi đã chứng minh game server xử lý đúng `board_naptien`**;
   - `KPAH_TOPUP_SERVER_ID`: ID server game theo kết quả kiểm tra; mặc định -1 (chưa xác minh).
5. Cấu hình callback URL trên đối tác: `https://TEN-MIEN-CUA-BAN/topup/card-callback.php`.
6. Kiểm tra HTTP 200/503 callback, lịch sử `storage/topup/orders`, khóa file; thử gửi callback trùng và đối soát lại từ admin.

Hai điều kiện `card_ready` và `game_queue_verified` đều phải đúng thì nút nạp thẻ mới bật. **Không gửi thẻ thật chỉ để test giao diện.**

## Cộng thủ công qua admin

Tìm username từ `team_user`, chọn nhân vật `tob_char` của tài khoản, chọn Xu/Lượng/Lượng khóa, nhập số lượng trực tiếp hoặc VNĐ để quy đổi. Bắt buộc có lý do và xác nhận nhân vật **đã đăng xuất game**. Trước khi cập nhật DB, script ghi bản ghi audit ở `storage/topup/grants`, dùng khóa file theo nhân vật và câu UPDATE có điều kiện tránh viết trùng do request song song.

Nếu đang `processing` / `needs_review`, dừng và kiểm tra số dư; **không bấm cộng lại**. Tác vụ chưa được kiểm thử trên database thật trong lượt làm việc này.

## Teamwork / GitHub Issues

- [Backend: xác minh bảng SQL + server nhận tài nguyên](https://github.com/thachdeptrai/web-kpah/issues/2)
- [Frontend: trang riêng + responsive](https://github.com/thachdeptrai/web-kpah/issues/3)
- [Admin: tỷ lệ, cấp tài nguyên, đối soát](https://github.com/thachdeptrai/web-kpah/issues/4)
- [Security: đối tác thẻ cào, callback, thử trùng lặp](https://github.com/thachdeptrai/web-kpah/issues/5)

Issues là bảng phân công để thành viên cộng tác trên GitHub; **chưa tự động có nhân sự hoặc agent AI khác đang làm**.
