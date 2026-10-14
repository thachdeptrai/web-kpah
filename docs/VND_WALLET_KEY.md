# Nâng cấp ví VNĐ và role key

## Thứ tự triển khai XAMPP / MariaDB

1. **Backup** database kpah, `kp_bank_orders`, `team_user`, và dữ liệu `storage/topup`.
2. Xác nhận VPS đã có cột số `team_user.vnd` (không cần thêm lại):
   `SHOW COLUMNS FROM team_user LIKE 'vnd';`
3. Chạy **một lần** `database/vnd_wallet_upgrade.sql` trên database `kpah`, không import lại dump SQL cũ.
4. `cd C:\xampp\htdocs` rồi `git pull --ff-only origin main`.
5. Trang admin: `/quan-tri/ty-le` → thưởng ngân hàng (mặc định +10%).
6. Người chơi: `/nap-ngan-hang` hoặc `/nap-the` → số dư `/vi-vnd` → đổi mốc.
7. Cấu hình Windows Task Scheduler tiếp tục chạy `tools/payos_cleanup.php` mỗi phút để retry đơn đã xác minh mà ví chưa hoàn tất.

### Luồng số dư

- Nạp ngân hàng 10.000đ, thưởng 10%: **+11.000 VNĐ** vào ví, **+10 Point** nếu đã bật chương trình Point.
- Nạp thẻ 10.000đ: cộng **số VNĐ thực nhận sau phí của nhà cung cấp**, không cộng thưởng ngân hàng; Point tính theo mệnh giá thẻ hợp lệ.
- Mỗi thanh toán được xác minh qua nguồn tin cậy và chỉ có **một dòng nguồn** trong `kp_vnd_ledger`.
- `kp_vnd_wallets` (InnoDB) là số dư chuẩn của web. `team_user.vnd` (MyISAM) được đồng bộ bằng **SET balance**, không `vnd=vnd+N`, tránh cộng nhiều lần từ webhook lặp.
- Khi bắt đầu sử dụng lần đầu, số `team_user.vnd` đang có sẽ được bảo toàn làm số dư mở ví. Các ứng dụng khác **không nên trực tiếp cập nhật `team_user.vnd`** trong khi website sử dụng ví này, vì web có thể đồng bộ lại.
- Không có quy trình cộng lại tiền của **đơn cũ**: chúng giữ `delivery_mode='game'`. Chỉ những đơn mới có `delivery_mode='wallet'` nhận VNĐ.
- Khi đổi mốc, tài khoản bị trừ VNĐ trong sổ InnoDB đúng một lần, sau đó trang web cấp Lượng trực tiếp cho nhân vật đã thoát game. Do bảng nhân vật là MyISAM, kết quả cấp không chắc chắn sẽ thành `needs_review`: **không bấm lại / không tự hoàn hoặc sửa số dư**; quản trị đối soát ở `/quan-tri/doi-soat`.
- Bảng mốc giữ nguyên các giá trị trong `kp_card_packages()`: 10k, 50k, 100k, 500k, 1M.
- Không lưu password hoặc API keys vào repo.

### Role key

Bảng `kpah_web_admins.role` đã là `VARCHAR(20)`, **không cần migration riêng**.

Role `key`:
- Được phép đăng nhập `/quan-tri/dang-nhap` và sẽ chuyển vào `/quan-tri/ma-gioi-thieu`.
- Được xem số liệu/mã giới thiệu và tạo mã mới, không được khóa/mở mã.
- Bị **403 ở backend** tại `/quan-tri`, mọi trang `/quan-tri/nap`, `/quan-tri/ty-le`, `/quan-tri/doi-soat`, API/quản trị không được phân quyền, v.v.
- Phiên đăng nhập vẫn bị kiểm tra role/is_active trong database trên mỗi request; CSRF, giới hạn đăng nhập và MFA nếu bật vẫn áp dụng.

Tạo một tài khoản cộng tác viên trên Navicat bằng một username chưa tồn tại, điền bcrypt hash (sinh bằng `php -r "echo password_hash('your-strong-password', PASSWORD_BCRYPT), PHP_EOL;"`) và `role='key'`. **Không dùng lại mật khẩu ngắn cho tài khoản quản trị thực tế.**

## Kiểm thử bắt buộc trên database thử nghiệm trước khi mở nạp

1. Chưa có `vnd` hoặc chưa chạy migration: website phải từ chối tạo QR/gửi thẻ.
2. Tài khoản đã có 123.000đ trong `team_user.vnd`: lần đầu mở `/vi-vnd` vẫn 123.000đ.
3. payOS 10.000đ: một invoice, cộng **11.000đ** lúc thưởng 10%, trạng thái `wallet_credited`.
4. Gọi cùng webhook và poll 100 lần: đúng một dòng `kp_vnd_ledger` / một lần cộng 11.000đ.
5. Admin thay bonus từ 10 sang 15%: hóa đơn **sau** thay đổi cộng 11.500đ cho 10k; hóa đơn cũ vẫn cộng 11k.
6. Provider xác minh thẻ 10k với thực nhận 8k: +8k vào ví; gọi lại callback không cộng trùng.
7. Nạp thất bại/chưa thanh toán: +0 vào ví; trạng thái cũ không thành ghi ví mới.
8. Đổi 10k từ ví: trừ một lần 10k, cấp 250 Lượng mở hoặc 500 Lượng khóa cho nhân vật offline khi thành công.
9. Spam/nhấn gửi lại cùng token đổi mốc: không trừ VNĐ thêm, không cộng Lượng thêm.
10. Không đủ VNĐ hoặc nhân vật sai tài khoản: từ chối trước khi trừ.
11. Nếu có `needs_review`, kiểm tra `kp_vnd_exchanges` và biến động `tob_char` trước khi xử lý thủ công.
12. Role `key`: tạo mã giới thiệu được, nhưng POST toggle và truy cập URL admin khác trả 403 (toggle bị từ chối và không thay đổi database).
13. Role `admin`: trang quà, admin nạp, đối soát và cài đặt bonus hoạt động.
14. Xác minh `kp_vnd_wallets.balance = team_user.vnd` sau khi ví hoạt động.

**Chưa kiểm thử live với payOS hoặc game server trên VPS chỉ từ Github; đừng nhận bản code này đã qua E2E production.**
