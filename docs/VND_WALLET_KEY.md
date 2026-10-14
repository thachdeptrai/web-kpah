# Ví VNĐ và quyền key — tương thích schema KPAH hiện có

## KHÔNG CẦN CHẠY SQL MỚI

Bản vá này dùng **bảng đã có**:
- `kp_bank_orders`: đơn payOS, không yêu cầu `delivery_mode`, `bonus_percent` hay các cột khác ngoài schema cũ.
- `team_user.vnd`: **cột do quản trị viên đã thêm**. Nếu trên một máy khác chưa có cột này, dừng nạp thật và kiểm tra cấu hình, không tự thêm bảng.
- `kpah_web_admins.role`: `VARCHAR(20)`, hỗ trợ giá trị `key` sẵn.
- Các bảng `kp_points_*` đang có tiếp tục hoạt động như cũ.

**Không tạo `kp_vnd_wallets`, `kp_vnd_settings`, `kp_vnd_ledger` hoặc `kp_vnd_exchanges`.** Tệp migration ví SQL cũ đã gỡ khỏi repo.

Dữ liệu tài chính mới được ghi trong thư mục riêng tư `storage/topup/vnd-journal/` (bị Apache chặn truy cập bằng .htaccess):
- Tiền thật trong `team_user.vnd`, thay đổi qua `UPDATE ... WHERE vnd=old_value` dưới khóa MySQL `GET_LOCK`.
- Mỗi webhook/card có một nhật ký nguồn duy nhất và ghi **prepared trước khi chạm MyISAM**, thành công mới chuyển **completed**.
- Nếu server tắt giữa chừng và kết quả không rõ, giao dịch không được cộng lại tự động; **cần đối soát** bằng nhật ký với số dư và đơn payOS.
- Các đơn cũ `completed` (Lượng) có `units > 0` không tự chuyển sang ví VNĐ, tránh nhận trùng.
- Đơn mới dùng trạng thái `wallet_creating`, `wallet_pending`, `wallet_crediting`, `wallet_credited`. Bonus được chốt tại thời điểm tạo QR vào `provider_reference` theo mẫu `VND_BONUS:10`. payOS vẫn được kiểm tra qua API bằng `order_code`.
- Thưởng mặc định **+10% ngân hàng**. Admin chỉnh tại `/quan-tri/ty-le`; mức % được giữ tại `storage/topup/bank-bonus.json`.
- Nạp thẻ hợp lệ chỉ cộng **VNĐ thực nhận sau chiết khấu**; không thưởng ngân hàng.
- Đổi mốc qua `/vi-vnd` trừ VNĐ một lần; cấp Lượng trực tiếp vào nhân vật đã thoát game. Nếu MyISAM trả kết quả chưa rõ, không tự hoàn hoặc cộng lại.
- Không xoá `storage/topup/` khi triển khai. Sao lưu nó cùng database.

## Quyền quản trị

Admin có thanh điều hướng chung trên `/quan-tri`, `/quan-tri/ma-gioi-thieu` và tất cả `/quan-tri/nap`, `/quan-tri/ty-le`, `/quan-tri/doi-soat`, `/quan-tri/nap-game`, `/quan-tri/diem-tich-luy`, `/quan-tri/cong-tai-nguyen`.

Tài khoản `key`:
- Tạo/khóa trong `/quan-tri/tai-khoan-key` (**chỉ admin**).
- Đăng nhập tại `/quan-tri/dang-nhap`, chuyển vào `/quan-tri/ma-gioi-thieu`.
- Chỉ **xem mã mình tạo, theo dõi lượt dùng của những mã đó và tạo mã giới thiệu**. Không khóa/mở mã; POST trái quyền trả HTTP 403. Không truy cập các trang admin khác (backend `kp_admin_require` kiểm tra role và trạng thái đang hoạt động mỗi lần).
- Mật khẩu cho tài khoản mới được băm bcrypt và yêu cầu ít nhất 8 ký tự (khuyến nghị dài hơn); không lưu dạng rõ.

## Triển khai

1. Backup database `kpah` và `storage/topup`.
2. `SHOW COLUMNS FROM team_user LIKE 'vnd';` kiểm tra cột có thật (bạn đã thêm).
3. `cd C:\xampp\htdocs` và `git pull --ff-only origin main`.
4. Đảm bảo tài khoản Windows chạy Apache có quyền đọc/ghi `storage/topup`.
5. Nếu Apache đang bật OPcache, khởi động lại Apache để code mới có hiệu lực.
6. Truy cập `/nap-ngan-hang`: bảng thưởng +10%, danh sách 2 đơn cũ vẫn hiển thị, không hiện lời nhắc chạy migration SQL.
7. Thử giao dịch ở môi trường test trước khi nhận nạp thật.

## Checklist kiểm thử

- Đơn cũ `completed`, `units = 250/500`: vẫn giữ lịch sử Lượng, không tự cộng VNĐ.
- Nạp payOS **10.000đ** với thưởng 10%: `team_user.vnd` tăng **11.000đ**, Point tăng 10 nếu chương trình Point đã kích hoạt.
- Webhook gửi lại 10–100 lần: không cộng trùng.
- Admin đổi thưởng 10 → 15%: chỉ QR mới áp dụng 15%.
- Thẻ xác minh được 8.000đ net từ mệnh giá 10.000đ: tăng 8.000đ VNĐ.
- Đổi mốc hai lần cùng request token: chỉ một lần trừ VNĐ, một lần cấp Lượng. Nếu việc ghi game không rõ, phải vào đối soát.
- Admin có menu chung trên tất cả trang; key chỉ thấy liên kết mã giới thiệu.
- Người dùng role key truy cập URL `/quan-tri`, `/quan-tri/ty-le`, `/quan-tri/tai-khoan-key` hoặc POST khóa mã → HTTP 403.

Kiểm thử thanh toán thật/game-server phải thực hiện trên VPS. Kiểm tra cú pháp tự động qua `.github/workflows/php-js-syntax.yml` không thay thế được kiểm thử giao dịch.
