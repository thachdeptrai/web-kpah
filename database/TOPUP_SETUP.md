# Quản lý tài nguyên KPAH — giai đoạn 1

## Trạng thái
- **Đã có:** `/admin/topup.php` — admin tìm đúng tài khoản và nhân vật, cộng thủ công Xu, Lượng không khóa, Lượng khóa, sửa tỷ lệ quy đổi và xem lịch sử.
- **Chưa có:** nạp thẻ tự động, webhook đối soát, ngân hàng tự động, trang người chơi thanh toán.
- Không cho phép người chơi gọi trực tiếp vào endpoint cấp tiền: thao tác này chỉ tồn tại trong phiên quản trị xác minh quyền `kpah_web_admins`.

## 1. Cài đặt database một lần
Sao lưu database trước. Import **`database/topup_admin.sql`** vào database game đang cấu hình cho website bằng Navicat hoặc phpMyAdmin.
Không import lại `kpah.sql` vì có thể ghi đè dữ liệu game đang dùng.

Lệnh tham khảo trong CMD nếu MySQL nằm trong XAMPP và tài khoản root không có mật khẩu:

```bat
C:\xampp\mysql\bin\mysql.exe -u root kpah < C:\xampp\htdocs\database\topup_admin.sql
```

Nếu database khác `kpah`, sửa tên cho đúng. Nếu MySQL có password, thêm `-p` và nhập khi hệ thống hỏi.

Script chỉ tạo hai bảng mới: `kpah_topup_rates` (InnoDB) và `kpah_topup_grants` (InnoDB). **Không thay đổi** `tob_char`, `team_user` hay tài khoản hiện hữu.

## 2. Vào panel
Đăng nhập `/admin/login.php` bằng tài khoản admin đã cấu hình, chọn **Cộng Xu, Lượng, Lượng khóa** hoặc vào thẳng `/admin/topup.php`.

Bảng chính:
- Xu = `tob_char.gold` (BIGINT UNSIGNED).
- Lượng thường / Lượng không khóa = `tob_char.luong` (INT UNSIGNED). Hai tên là **một** tài nguyên.
- Lượng khóa = `tob_char.luonglock` (INT UNSIGNED).

Panel cho phép nhập số tài nguyên cộng trực tiếp, hoặc nhập **VNĐ** để quy đổi theo tỷ lệ đã đặt.
Tỷ lệ mặc định: **10.000 VNĐ = 100 Lượng không khóa** hoặc **200 Lượng khóa**. Xu mặc định **0** (chưa bật quy đổi VNĐ); admin có thể sửa cả ba tỷ lệ.
Số tiền VNĐ **không cần chia hết cho 10.000đ** và không có gói nạp cố định. Ví dụ 5.000đ đổi 50 Lượng thường hoặc 100 Lượng khóa. Quy đổi bằng phép tính số nguyên, phần lẻ dưới 1 đơn vị bị làm tròn xuống và không chấp nhận số tiền quá nhỏ cho ra 0 tài nguyên.

## 3. Lưu ý trước khi cộng
1. Bảo đảm nhân vật đã **thoát game**. Database `tob_char` đang dùng **MyISAM**, không hỗ trợ rollback.
2. Tìm đúng tài khoản (theo `team_user.username`), rồi chọn nhân vật từ `tob_char.userId = team_user.id`.
3. Nhập loại và số lượng, ghi lý do, tick xác nhận, bấm nút cộng **một lần**.
4. Kiểm tra dòng `applied` và số dư ghi ở bảng lịch sử.
5. Nếu có dòng `processing` hoặc `needs_review`, **dừng cộng** cho nhân vật đó và đối chiếu SQL/nhật ký. Không gửi giao dịch khác để “thử lại”.
6. Tài nguyên có thể được cache trong game server; cần kiểm tra cơ chế lưu nhân vật của server trước khi nạp thật hoặc bán tự động.

Không tự động coi một dòng nhập VNĐ ở admin là tiền thực nhận. Đây chỉ là **công cụ tính quy đổi**, không phải một giao dịch đã thanh toán.

## 4. Kiểm thử trên staging
- Import SQL và sao lưu dữ liệu.
- Chạy `php -l admin/topup.php`.
- Đăng nhập admin, kiểm tra trang và quyền truy cập khi chưa login / khi tài khoản admin bị vô hiệu.
- Dùng tài khoản/nhân vật test **offline**, cộng 1 Xu, 1 Lượng thường, 1 Lượng khóa; kiểm tra đúng cột và chỉ cộng một lần.
- Submit lại cùng POST: mã `request_key` duy nhất phải chặn cộng trùng.
- Tạo bản ghi `needs_review` thử nghiệm: nhân vật phải bị chặn cộng tiếp.
- Mở trang trên điện thoại, thử ô tìm kiếm và lịch sử ngang.
- Kiểm tra server game load/save: số dư có bị hoàn nguyên khi người chơi đăng nhập/thoát hay không.

## 5. Tích hợp nạp thẻ tự động sau này
Cần chọn nhà cung cấp hỗ trợ thẻ, lấy API chính thức, thông số trạng thái thành công/thất bại, chữ ký webhook và cơ chế idempotency theo mã giao dịch. Không được cộng tài nguyên chỉ dựa trên việc người chơi nhập mã thẻ hay API trả “đã gửi”.
Để bảo đảm an toàn trên MyISAM và game server cache, nên dùng cơ chế **hàng đợi cấp thưởng do game server tiêu thụ**, hoặc một API nội bộ của game server xác thực và ghi theo giao dịch, trước khi bật cộng tự động cho người chơi.

Không commit token, secret API hoặc thông tin thanh toán vào repo.
