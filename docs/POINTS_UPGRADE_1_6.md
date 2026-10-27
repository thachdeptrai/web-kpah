# Nâng cấp Point website KPAH (giai đoạn 1–6)

## Triển khai an toàn

Backup database kpah. Trong Navicat chọn đúng database và chạy file
database/points_upgrades_1_6.sql. File tạo 5 bảng InnoDB mới, không ALTER
game MyISAM, không xóa Point cũ hoặc phát lại quà.

Các bảng: kp_points_reward_display (1), kp_points_redemption_events (2),
kp_points_redemption_targets (3), kp_points_audit (4),
kp_points_reward_tiers (6). Giai đoạn 5 là đối soát chỉ đọc.

Các migration riêng trong database/ vẫn tồn tại để kiểm toán.

## Tổng hợp thay đổi

1. Cửa hàng: hình ảnh lấy từ /res/icon/{numeric_id}.png, ID vật phẩm và
mô tả options dạng text, không phải lệnh phát vật phẩm Java. HTML được escape.
2. Duyệt đơn: pending -> approved -> delivering -> fulfilled. Từ chối và hoàn
Point chỉ ở pending/approved. Không hoàn tự động khi đã bắt đầu phát quà.
3. Nhân vật: PHP xác minh tob_char.id và tob_char.userId thuộc account
đăng nhập, đang hoạt động; chụp lại tên để Admin phát đúng người.
4. Nhật ký: điểm trước/sau, hành động, Admin, giây chính xác, IP và thiết bị.
IP là địa chỉ kết nối server; GeoIP chỉ ước lượng và có thể không có.
Webhook được gắn nhãn hệ thống, không giả làm thiết bị người chơi.
5. Đối soát: chênh lệch ví / tổng ledger, đơn trên 24h, payOS order
thiếu sổ Point. Chỉ đọc, mỗi nhóm tối đa 100 bản ghi, không auto sửa số dư.
6. Thành viên: Đồng (0), Bạc (1.000), Vàng (5.000), Kim Cương (15.000)
tính theo total_earned. Admin đặt hạng tối thiểu cho quà; PHP kiểm tra
hạng trước khi trừ Point, kể cả khi nút HTML bị sửa.

## Kiểm tra bắt buộc

- PHP lint, CI Routes and account regression và tests/points_workflow_integration.php
trên MariaDB staging; chạy tests/points_security_smoke.php.
- Thử mô tả chứa dấu < và >, JavaScript injection trong options, ký tự SQL đặc
biệt, icon URL độc hại: tuyệt đối không được thực thi.
- Tạo yêu cầu dùng nhân vật khác tài khoản phải bị chặn trước khi mất Point.
- Replay token không được trừ Point lần hai, từ chối chỉ hoàn một lần.
- Duyệt -> đang phát -> xác nhận giao đúng thứ tự; ghi nhật ký tương ứng.
- Kiểm tra trang User chỉ nhìn thấy dữ liệu của mình; Admin xem toàn bộ.
- Kiểm tra độ rộng mobile và các bảng cuộn ngang.
- So sánh số dư Point và ledger trước/sau khi mở báo cáo đối soát; không được thay đổi.

## Giới hạn bảo mật

JavaScript phía trình duyệt luôn có thể xem/định dạng lại kể cả minify.
Bản points-guard.js chỉ chống nhấn lặp ở giao diện, không làm xác thực
hoặc cấp Point. Quyền Admin, giá Point, tỷ lệ và quyền sở hữu nhân vật
phải luôn kiểm tra tại PHP/backend.

Không lưu bí mật trong JS; không bảo đảm mã JS không dịch ngược được.
Apache phải thực thi PHP và chặn truy cập source app/, storage/, config/.
Không tấn công SQL injection hoặc replay webhook trên production.
Mọi kiểm tra CI không thay thế QA game + provider giả lập/kiểm tra VPS thật.
Lịch sử thiết bị/IP trước lúc thêm audit không thể khôi phục.
