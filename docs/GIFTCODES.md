# Giftcode trên website NGHIỆN KPAH

## Nguồn dữ liệu thực tế

- Bảng game `giftcode`: `id`, `giftcode`, `xu`, `luong`, `luongLock`, `item`, `expire`, `limit_use`, `type`.
- Bảng game `giftcode_log`: `id`, `giftcode`, `player`, `item`, `xu`, `luong`, `luongK`.
- Chỉ mở rộng `giftcode.item` từ `VARCHAR(255)` sang `TEXT`; dữ liệu và token cũ không đổi. `type=0` và `limit_use>0` là những điều kiện hiện được dùng trong mã Java đã cung cấp. **Trường `expire` không được mã Java kiểm tra** trong nhánh nhận code đang được sử dụng, nên UI không hứa hẹn ngày hết hạn.
- Mã mới không tự xuất hiện trên trang chủ. Admin phải tích **Hiển thị trên trang chủ**; metadata lưu tại `storage/giftcode/catalog.json`.
- Công cụ quản trị nằm tại `/quan-tri/giftcode`, chỉ role `admin` truy cập; role `key` bị từ chối. Phiên quản trị, CSRF, bộ giới hạn số thao tác dùng guard hiện có.
- Chức năng `Sao chép` trên trang chủ chỉ copy mã. Người chơi vào game để nhận quà. Website không trực tiếp cập nhật ví hay cộng tài nguyên.

## Vận hành

1. Sao lưu DB và `storage/` trước khi triển khai.
2. Cho phép PHP ghi `storage/giftcode` (thư mục web server chặn HTTP trực tiếp).
3. Chạy `database/giftcode_item_options.sql` để mở rộng cột item; form vẫn chặn vượt 255 byte nếu migration chưa được áp dụng.
4. Áp `game-server-patch/giftcode-item-options.patch`, build/restart server thử nghiệm và xác nhận một token `DBO` nhận/relog đúng.
5. Chỉ sau khi bước 4 thành công, đặt `KPAH_GIFTCODE_DBO_ENABLED=1` cho website. Khi cờ đang tắt, item mặc định vẫn lưu bằng token `DB` cũ nhưng UI/backend đều khóa option tùy chỉnh.
6. Tạo giftcode, chọn item thực từ `data_item`, đặt số lượng/thời hạn item và chọn option thực từ `data_attribute`. Ảnh chỉ dùng `res/icon/<data_item.xstart>.png`; file thiếu được thay bằng placeholder.
7. Tích hiển thị nếu muốn công khai code, chọn **Tạm khóa** nếu chưa cho người chơi nhập.
8. Sau khi lưu, mở trang chủ và kiểm tra danh sách. Mã mới chỉ hiển thị nếu `type = 0`, `limit_use > 0` và admin đã công khai.
9. Tại trang quản trị, lọc lịch sử theo code hoặc tên nhân vật. Bảng `giftcode_log` không có timestamp; ID chỉ phản ánh thứ tự ghi tương đối.
10. **Xóa:** code chưa ghi nhận sử dụng bị xóa hẳn; code có lịch sử được khóa và ẩn để giữ truy vết.

## Bộ chọn vật phẩm và tạo hàng loạt

- Bộ chọn item lấy **toàn bộ** `data_item` và tìm kiếm không phân biệt dấu theo tên hoặc ID (ví dụ `#250`). Danh sách render từng trang **80 vật phẩm**; bấm **Hiển thị thêm vật phẩm** để xem tiếp, thay cho giới hạn cũ 180 item. Chỉ những item thật trong DB mới được thêm vào Giftcode.
- Tại `/quan-tri/giftcode`, phần **Tạo hàng loạt Giftcode** cho nhập tiền tố, **1–100 mã/lần**, Xu/Lượng/Lượng khóa, số lượt dùng **trên mỗi mã**, tiêu đề/ghi chú và item/options dùng chung cho cả đợt. Mỗi mã có hậu tố 16 ký tự hex sinh từ `random_bytes`, kiểm tra trùng với database và không được thay đổi parser Java.
- Bộ tạo dùng lại `kp_giftcode_validate_reward_payload()`, `kp_giftcode_serialize_rewards()`, kiểm tra độ dài cột SQL và Admin CSRF/role/rate limit. Giới hạn tối đa **5 đợt/giờ trên mỗi Admin**, chỉ được tạo lại bằng nonce mới; gửi lại cùng POST không tạo thêm đợt.
- Mã mới luôn **ẩn trên trang chủ**, kể cả khi đã bật cho nhập trong game. Có tùy chọn **Tạm khóa** toàn bộ đợt nếu chưa muốn phát. Danh sách mã mới tạo được hiển thị ngay sau khi lưu để Admin sao chép.
- Bảng game có thể dùng **MyISAM**, nên không được giả định có transaction. Bộ tạo insert mỗi code **type=1 (khóa)**, ghi metadata rồi mới mở `type=0`. Nếu lỗi giữa chừng, các code đã hoàn tất được giữ nguyên, code đang tạo dở bị khóa và Admin được thông báo; **không gửi lại tự động** khi chưa đối chiếu.
- Kiểm thử: `php tests/giftcode_bulk_smoke.php` và `node tests/giftcode_picker_ui.test.js`; các test sử dụng DB giả/các item giả, **không** sinh Giftcode trên production.

## Giới hạn và bảo mật

- Không thay đổi chuỗi giftcode sau khi tạo vì lịch sử đang lưu tên code dạng text, không phải khóa ngoại.
- Server Java được cung cấp dùng SQL nối chuỗi và xử lý ghi log/cập nhật limit riêng, nên cần harden server game trước khi có thể bảo đảm chống race-condition/SQL injection.
- `expire` của bảng game không đồng nghĩa với lịch công khai. Không hiển thị ngày hết hạn giả.
- Website kiểm tra lại item ID, option ID, kiểu số, giới hạn mảng Java và dung lượng cột SQL ở backend. JSON từ trình duyệt không được ghi trực tiếp xuống DB.
- Khi chỉnh sửa, token đặc biệt cũ (`PT`, `GEM`, `PET`, v.v.) và token `DB` không còn có trong catalog được giữ nguyên, không hiện thành dữ liệu giả.
- Dữ liệu metadata công khai bị giới hạn kích thước và HTML luôn escape. Không đưa tên người chơi trong lịch sử ra trang công khai.
- **Backup cả `storage/giftcode/catalog.json`** khi chuyển VPS vì file này tách khỏi Git. Nếu file chưa tồn tại, trang chủ hiển thị trạng thái chưa mở giftcode.
