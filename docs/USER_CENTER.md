# Player Center (Giftcode, Thông báo, Đơn nạp, Bảo mật)

## Triển khai
Sau khi cập nhật mã nguồn trên Windows/XAMPP, **sao lưu database trước**, sau đó chạy `database/user_center.sql` đúng một lần qua Navicat hoặc mysql. Script là các lệnh CREATE TABLE IF NOT EXISTS, không sửa bảng game MyISAM. Nếu chưa chạy migration, trang thông báo và lịch sử bảo mật sẽ hiển thị trạng thái chưa sẵn sàng.

## Trang người chơi
- `/giftcode-cua-toi`: lịch sử thực từ `giftcode_log`, lọc theo bảng `tob_web_info_char` với `userid` của phiên đang đăng nhập. Game hiện không lưu timestamp trong log.
- `/thong-bao`: thông báo ghi trong `kp_user_notifications`, có thể đánh dấu đã đọc bằng CSRF-protected POST, chỉ ảnh hưởng thông báo của người dùng hiện tại.
- `/theo-doi-don-nap`: tổng hợp các đơn thẻ thuộc file history hiện có và đơn PayOS từ `kp_bank_orders` có `user_id` tương ứng. Trạng thái là ảnh chụp từ dữ liệu, không tự động xác nhận thanh toán. Không hiển thị PIN/serial/URL checkout.
- `/lich-su-bao-mat`: đăng nhập website thành công và đổi mật khẩu kể từ khi có hệ thống log. Không ghi lại mật khẩu, OTP, session ID hay IP đầy đủ.
- UI Toast góc phải: `window.kpToast('success'|'error', 'Tiêu đề', 'Lý do')`; chỉ sử dụng `textContent`, không render HTML tùy ý.

## Phạm vi thông báo
Phiên bản này tạo thông báo bảo mật khi **đổi mật khẩu thành công**. Không suy diễn hoặc tự tạo thông báo các đơn nạp từ lịch sử cũ. Các pipeline thanh toán tương lai có thể gọi `kp_center_notify` tại chính bước xác minh trạng thái server-side, không gọi khi chỉ có URL callback hoặc khi đơn còn chờ.

## Giới hạn
- Các đăng nhập game/Java và sự kiện trước lúc chạy migration sẽ không xuất hiện trong lịch sử website.
- Việc lưu security log là best-effort (không khiến đăng nhập hoặc đổi mật khẩu bị khóa nếu bảng nhật ký tạm lỗi).
- Do dữ liệu người dùng nhạy cảm, tất cả trang yêu cầu session đã xác minh và truy vấn có tham số ràng buộc theo account ID.
- Người dùng có thể nhìn thấy thông báo tại góc phải cho các luồng đã tích hợp. Tính năng toàn bộ admin và callback thanh toán cần bổ sung từng thao tác, không được tự quảng cáo là đã đồng bộ toàn hệ thống.
