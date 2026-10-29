# Diễn đàn báo lỗi & góp ý KPAH

## Cài đặt database (bắt buộc)

1. Sao lưu cả database website/game `kpah` và database Point `kpah_points` trước.
2. Import [`database/forum_feedback.sql`](../database/forum_feedback.sql) vào database Point **`kpah_points`** bằng Navicat. Chỉ tạo ba bảng `kp_forum_*`, không đụng tới bảng game hay dữ liệu Point có sẵn.
3. Trước khi thử gửi bài/phản hồi, phải có hai bảng thông báo trong `kpah`: chạy [`database/forum_notifications_kpah.sql`](../database/forum_notifications_kpah.sql). Kiểm tra cả `kp_admin_notifications` và `kp_user_notifications` đều dùng InnoDB, rồi mới bật ghi Forum.
4. Website tái sử dụng cấu hình `points_db.name` hoặc `KPAH_POINTS_DB_NAME` (mặc định `kpah_points`). PHP truy cập hai schema trên cùng MySQL server: `kpah.team_user` để tra tên thật khi cần, `kpah_points.kp_forum_*` để đọc/ghi bài viết. Không tạo cấu hình `forum_db` riêng.
5. Git pull trên `main`, kiểm tra:
   - `/dien-dan`: người chơi xem bài công khai; đăng nhập mới gửi được.
   - `/quan-tri/forum`: chỉ role `admin` vào xử lý và trả lời.
   - `/cong-dong` và menu công khai có đường dẫn đến diễn đàn.
   - Menu khu tài khoản có "Báo lỗi & góp ý"; thông báo Admin và User trỏ đến diễn đàn.
6. Thử trên môi trường staging: gửi báo lỗi bình thường, kiểm tra hiển thị tên bị che, Admin phản hồi, thay trạng thái, ẩn bài, kiểm tra chỉ tác giả đọc được phản hồi riêng.

## Chuyển bài viết cũ nếu từng dùng database game

Chỉ áp dụng nếu đã từng lưu bài/phản hồi vào `kpah.kp_forum_*`: dừng ghi forum, sao lưu và import [script chuyển dữ liệu](../database/forum_move_existing_to_kpah_points.sql) sau khi tạo bảng mới tại `kpah_points`. **Chỉ chạy một lần khi bảng đích rỗng**; so sánh số dòng, ID, trạng thái và phản hồi trước khi bật ghi lại. Giữ các bảng gốc để rollback. Nếu chưa tạo forum trong `kpah`, **không chạy script chuyển dữ liệu**.

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

## Thông báo tự động hai chiều

- Khi người chơi tạo báo lỗi/góp ý, cùng transaction với `kpah_points.kp_forum_topics` hệ thống ghi một thông báo chưa đọc vào `kpah.kp_admin_notifications`, link `/quan-tri/forum`. Admin thấy chấm đỏ trên menu Forum và chuông thông báo; các bài `new` vẫn hiển thị chờ xử lý dù đánh dấu inbox đã đọc.
- Khi Admin đăng phản hồi hoặc đổi trạng thái/ẩn hiện bài, cùng transaction với `kpah_points.kp_forum_replies` và `kpah_points.kp_forum_actions` hệ thống ghi vào `kpah.kp_user_notifications` cho **đúng user_id**. Người chơi xem trong `/thong-bao`, nhận toast ở khu tài khoản/diễn đàn và đọc phản hồi tại mục **Bài của tôi** kể cả bài ẩn.
- Nếu không có thay đổi, không phát sinh thông báo thừa. Gửi lại cùng `submission_token` không thể tạo thông báo Admin trùng.
- **Yêu cầu SQL:** Import [`database/forum_notifications_kpah.sql`](../database/forum_notifications_kpah.sql) **vào MySQL server có database `kpah`** để tạo hai bảng thông báo chung `kpah.kp_admin_notifications` và `kpah.kp_user_notifications`, đặc biệt khi gặp `SQLSTATE[42S02] ... kpah.kp_admin_notifications doesn't exist`. Lệnh được gắn rõ schema `kpah`, chạy lại không xóa dữ liệu. Hoặc import đầy đủ [`database/user_center.sql`](../database/user_center.sql) khi đã chọn đúng database `kpah` (bao gồm cả bảng lịch sử bảo mật). Các bảng Forum vẫn ở **`kpah_points`**. Hai schema phải cùng một máy chủ MySQL/MariaDB; cả bảng thông báo và bảng Forum dùng InnoDB để commit/rollback chung.
- Không công khai tên tài khoản thật trong thông báo Forum; tên thật chỉ ở khu quản trị có kiểm soát.
