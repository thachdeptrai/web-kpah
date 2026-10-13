# KPAH – User account portal

## Trang và luồng
- `login.php`: đăng nhập tài khoản game.
- `register.php`: đăng ký hiện có; có liên kết quay lại đăng nhập.
- `home.php`: trang chủ tài khoản, chỉ truy cập sau đăng nhập.
- `forgot-password.php`: khôi phục mật khẩu bằng mã gửi tới email đã liên kết.
- `app/api/logout.php`: đăng xuất.
- `app/api/user-profile.php`: cập nhật email và số điện thoại.
- `app/api/forgot-password.php`: gửi mã và đặt lại mật khẩu.

## Dữ liệu và bảo mật
Không thêm bảng hoặc cột mới. Dùng các cột đã có trong `team_user`: `id`, `userid`, `username`, `password`, `email`, `phone`, `regdate`. Mật khẩu được lưu theo định dạng tương thích server game hiện tại (`PASSWORD()` kiểu SHA1 kép); không đổi định dạng vì có thể làm game không đăng nhập được.

Đổi email yêu cầu mã 6 chữ số gửi đến **email mới**, có hạn dùng và được ràng buộc với user đang đăng nhập. Email trùng tài khoản khác bị từ chối. Khôi phục mật khẩu gửi mã tới email đang liên kết và không tiết lộ công khai tài khoản có tồn tại hay không. Mọi endpoint kiểm tra session, CSRF và giới hạn tần suất.

## Triển khai
1. Đảm bảo cấu hình DB trong `config.register.local.php` đang trỏ tới database game.
2. SMTP trong cấu hình local phải có `smtp_host`, `smtp_user`, `smtp_pass`, `from_email`; với Gmail dùng App Password, không dùng mật khẩu đăng nhập Gmail thông thường.
3. Tài khoản MySQL của website cần quyền `SELECT` và `UPDATE` trên `team_user` cho trang hồ sơ/khôi phục mật khẩu. Luồng đăng ký hiện tại cần thêm `INSERT`.
4. Mở `/dang-nhap`, đăng nhập bằng username và mật khẩu của tài khoản game. Sau đăng nhập chuyển sang `/tai-khoan`.
5. Nếu quên mật khẩu hoặc cập nhật email không gửi được mã, kiểm tra log PHP/Apache trên máy chủ và thông tin SMTP. Không đưa mật khẩu SMTP vào Git.

## Lưu ý
Chưa thể chạy thử với database/SMTP thực tế của máy chủ từ GitHub. Hãy thử trên môi trường staging trước khi dùng thật; đặc biệt xác nhận server game vẫn nhận mật khẩu theo định dạng hiện có.
