# KPAH — Nạp ngân hàng payOS

1. Trong Navicat, chọn database kpah rồi chạy `database/payos_bank.sql` **một lần**. Migration dùng CREATE TABLE IF NOT EXISTS (InnoDB) và không xóa bảng/dữ liệu cũ.
2. Tại https://my.payos.vn tạo kênh thanh toán kết nối tài khoản KienlongBank; lấy **Client ID, API Key, Checksum Key** của kênh thanh toán.
3. Trên VPS sao chép `config/payos.local.sample.php` thành `config/payos.local.php`, điền 3 khóa. File thật đã được .gitignore bỏ qua.
4. Trong payOS, xác nhận webhook: `https://nghienkpah.io.vn/api/payos-webhook`.
5. Kiểm tra trang `https://nghienkpah.io.vn/nap-ngan-hang`. PayOS xử lý QR trên trang checkout của họ; không chuyển tiền trực tiếp ngoài đơn.
6. Chỉ bật `game_queue_verified=true` và `server_id` đúng ở `config/topup.local.php` sau khi xác minh game server thực sự đọc/tiêu thụ `board_naptien`. Nếu chưa bật thì đơn đã trả tiền được lưu `paid_waiting_delivery`, chờ đối soát thủ công.

Khác API thẻ TheCaoSieuToc (Partner ID/Key), payOS bắt buộc 3 thông tin Client ID, API Key và Checksum Key. Không dán khóa vào GitHub. Mức thưởng ngân hàng đang dùng bảng mệnh giá 10k/50k/100k/500k/1.000k, **không trừ chiết khấu xử lý thẻ**. Trạng thái redirect trình duyệt không chứng minh tiền đã vào ngân hàng; backend kiểm tra trạng thái trực tiếp từ API payOS.

Yêu cầu PHP 8.0+ PDO MySQL, cURL, HTTPS, Apache mod_rewrite và database InnoDB. Chưa test tiền thật, cần kiểm tra webhook, payment pending, trùng callback và game queue trước khi mở chính thức.


## Tự hủy đơn chưa thanh toán sau 10 phút

payOS link được đặt `expiredAt = lúc tạo + 600 giây`. Website chuyển trình duyệt trực tiếp bằng HTTP 303 tới `checkoutUrl` của payOS khi nhấn Tạo QR (không cần popup/iframe).

Bộ dọn đơn chỉ xóa khi payOS **xác nhận `CANCELLED` hoặc `EXPIRED`**, với `amountPaid = 0`. Nếu API mất kết nối, trạng thái chưa rõ hoặc có dấu hiệu tiền đã vào, đơn **được giữ lại** để đối soát. Đơn thanh toán thành công và `paid_waiting_delivery` / `needs_review` / `crediting` / `queued` tuyệt đối không bị xóa tự động.

Để chạy ngay cả khi không có ai vào website, mở Windows Task Scheduler, tạo task lặp **mỗi 1 phút**, chạy:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\tools\payos_cleanup.php
```

Chỉ chọn `Run whether user is logged on or not` khi tài khoản chạy có quyền truy cập cấu hình riêng, database và internet. Có thể chạy thử lệnh trên CMD trước để đọc số đơn đã dọn. Trang nạp không gọi API dọn đơn nhằm tránh chậm khi nhà cung cấp phản hồi lâu; Task Scheduler đảm nhiệm việc dọn tự động. Không chạy lệnh này qua web.

**Quan trọng:** `creating` nhưng chưa chắc tạo link thành công sẽ không xóa tự ý khi payOS không xác nhận trạng thái. Đây là lựa chọn an toàn để tránh xóa đơn người chơi đã trả tiền.

## QR nhúng và giới hạn lệnh nạp

- Bấm **Tạo QR** sẽ hiển thị giao diện payOS ngay trên `/nap-ngan-hang` (SDK trực tiếp từ `cdn.payos.vn`). Nếu iframe bị chặn, vẫn có link mở trang payOS.
- Trang tự đọc trạng thái qua `/api/payos-status` mỗi **5 giây**, chỉ đọc đơn thuộc tài khoản đang đăng nhập; không dựa vào kết quả JS để cấp tiền.
- Khi người chơi tải lại trang trong 10 phút, QR chưa thanh toán được hiển thị lại, không tạo đơn mới.
- **Mỗi tài khoản được tạo tối đa 3 lệnh nạp trong cửa sổ 15 phút**, kể cả khi F5 hoặc dùng nhiều thiết bị. Giới hạn lưu tại `storage/ratelimit/`, không tự đặt lại khi hủy đơn. Nếu vượt hạn mức, web thông báo thời gian chờ.
- Nếu hoàn tất thanh toán nhưng webhook đến chậm, trạng thái có thể chưa cập nhật ngay; đối soát vẫn căn cứ trên API payOS.

## Đã trả tiền nhưng đứng ở paid_waiting_delivery

- Đây là đơn payOS đã được xác minh trả tiền nhưng chưa ghi `board_naptien` do cờ `game_queue_verified=false`.
- Xác nhận trực tiếp mã `svID` mà game server dùng để đọc `board_naptien`. Trong schema SQL gốc, `svID` có **DEFAULT -1**; không được tự coi -1 là lỗi hoặc tự đổi sang 1 nếu chưa kiểm tra consumer.
- Trên VPS, sau khi xác minh game server xử lý queue, đặt `'game_queue_verified'=>true` trong `config/topup.local.php`. Nếu có biến môi trường `KPAH_TOPUP_GAME_QUEUE_VERIFIED`, nó sẽ ghi đè file local.
- Task Scheduler chạy `tools/payos_cleanup.php` mỗi phút sẽ kiểm tra lại tối đa 20 đơn `paid_waiting_delivery` mỗi lượt. Có thể chủ động vào `/quan-tri/doi-soat` và bấm Kiểm tra. Không tự tạo đơn mới hoặc nạp lại.
- Trạng thái `queued` chỉ có nghĩa là đã thêm vào hàng chờ game, chưa khẳng định nhân vật đã nhận. Nếu `needs_review` hoặc `crediting`, đối chiếu thủ công trước khi ghi bổ sung để tránh cấp hai lần.
