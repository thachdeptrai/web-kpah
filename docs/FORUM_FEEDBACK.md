# Diễn đàn báo lỗi & góp ý KPAH

## Cài đặt database (bắt buộc)

1. Sao lưu database website/game `kpah` trước.
2. Import [`database/forum_feedback.sql`](../database/forum_feedback.sql) bằng Navicat trong **database game/web `kpah`**, không import vào database Point `kpah_points`. Script CREATE TABLE IF NOT EXISTS, không sửa dữ liệu game cũ.
3. Website tự dùng kết nối `kp_db()` hiện tại; tài khoản MySQL của PHP phải được phép đọc/ghi ba bảng `kp_forum_topics`, `kp_forum_replies` và `kp_forum_actions`.
4. Git pull trên `main`, kiểm tra:
   - `/dien-dan`: người chơi xem bài công khai; đăng nhập mới gửi được.
   - `/quan-tri/forum`: chỉ role `admin` vào xử lý và trả lời.
   - `/cong-dong` và menu công khai có đường dẫn đến diễn đàn.
   - Menu khu tài khoản có "Báo lỗi & góp ý"; thông báo Admin và User trỏ đến diễn đàn.
5. Thử trên môi trường staging: gửi báo lỗi bình thường, kiểm tra hiển thị tên bị che, Admin phản hồi, thay trạng thái, ẩn bài, kiểm tra chỉ tác giả đọc được phản hồi riêng.

## Dữ liệu lưu trữ

- `kp_forum_topics`: user_id nội bộ, **public_alias chỉ chứa một chữ đầu và bốn dấu sao** (ví dụ `t****`), loại bài, nền tảng, tiêu đề, mô tả, trạng thái, hiển thị, timestamps. **KHÔNG** có cột lưu plaintext username/email.
- `kp_forum_replies`: phản hồi Admin, theo topic và admin_id nội bộ, lịch sử append-only.
- `kp_forum_actions`: nhật ký trạng thái/hiển thị trước và sau, actor admin ID, timestamp.
- Các trạng thái: `new`, `reviewing`, `resolved`, `declined`.
- Chỉ các bài `visibility=public` xuất hiện trong danh sách công khai, có phân trang. Tác giả đã đăng nhập đọc các bài của chính họ ở mục **Bài của tôi**, kể cả bài bị ẩn. Admin đọc toàn bộ với phân trang.

## Ẩn tên & ngăn lộ dữ liệu

- **Truy vấn công khai** chỉ SELECT các cột cần hiển thị và không JOIN `team_user`; không trả về `user_id` hay `account_name`.
- **Trong lúc gửi**, server lấy tài khoản từ session hợp lệ và `team_user`, không tin username/userId do browser tự khai. Tên công khai được sinh cố định như `a****` không tiết lộ độ dài. Nếu tác giả tự ghi username của mình trong tiêu đề/nội dung, server tự thay bằng tên che trước khi lưu.
- **Admin** cần session/role `admin` còn hiệu lực, kiểm tra CSRF và rate limit. Tên thật chỉ SELECT ở trang quản trị.
- Phản hồi Admin tự che username của chủ bài nếu Admin gõ nhầm trong nội dung. Các bài chứa email, số điện thoại, mật khẩu, token, hướng dẫn khai thác lỗ hổng... được tự đưa về `hidden` để xử lý riêng. Admin có thể ẩn bài sau khi đã xuất hiện công khai. **Bộ lọc tự động không thay thế kiểm duyệt thủ công.**
- Nội dung không được render dưới dạng HTML; PHP dùng `kp_e()`. Mọi thao tác ghi dùng prepared statement, role guard, CSRF, giới hạn gửi; request nonce chống gửi trùng. Ghi lịch sử trạng thái và phản hồi trong transaction.

**Giới hạn bảo mật quan trọng:** Che tên và hạn chế dữ liệu trả về **không ngăn được người chiếm quyền điều khiển toàn bộ PHP/server/database** đọc bảng `team_user`, vì các chức năng game/login khác vẫn dùng cùng thông tin kết nối SQL. Để bảo vệ chống RCE hoặc lộ database credential phải thực hiện hardening hệ thống, tách quyền tài khoản MySQL theo service và giới hạn truy cập tới `team_user` tại tầng DB. Không thể gọi việc che tên trong giao diện là mã hóa danh tính trong database.

## Kiểm tra tự động

GitHub Actions workflow `Forum feedback security and integration` chạy PHP 8.0 / 8.3 với MariaDB test database `kpah_forum_test`. Test che tên, xác thực alias, không SELECT username ở truy vấn công khai, phân biệt public/hidden, reply, không tiết lộ admin_id và chống trùng request token. Không dùng thông tin tài khoản thật.

## Vận hành

- Diễn đàn lưu nội dung theo thời gian, không tự xóa phản hồi hoặc nhật ký xử lý.
- Bài liên quan thanh toán/có dữ liệu nhạy cảm: giữ **Ẩn**, Admin liên hệ riêng qua kênh an toàn để xác minh; không yêu cầu người chơi công khai mã đơn, token hay ảnh chứa thông tin đăng nhập.
- Khi cần xóa dữ liệu cá nhân theo yêu cầu hợp lệ, cần quy trình riêng về lưu trữ/ẩn danh và đối soát; không tùy ý xóa nhật ký quản trị.
