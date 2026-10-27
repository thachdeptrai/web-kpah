# Tách dữ liệu Point sang database riêng (MySQL/MariaDB)

## Kiến trúc
- Database game/web hiện tại (ví dụ `kpah`): `team_user`, `tob_char`, `kp_bank_orders`, notifications, catalog vật phẩm, payOS.
- Database Point mới (ví dụ `kpah_points`): tất cả **11 bảng `kp_points_*`** gồm program, wallet, ledger, rewards, redemptions, metadata, audit, tiers và redemption_control.
- Cả hai schema **bắt buộc cùng một server MySQL/MariaDB và cùng tài khoản PDO có quyền trên cả hai**. Web dùng một PDO, query `kp_points_*` theo schema đủ tên; các giao dịch InnoDB vẫn nằm trong một transaction. Không hỗ trợ hai host MySQL độc lập hoặc tách server từ xa.
- Trước khi cấu hình database Point, hành vi cũ trên database `kpah` được giữ nguyên.

## Cài mới (chưa có ví hay lịch sử Point)

1. Trong Navicat, sử dụng user MySQL có quyền tạo database. Import `database/points_separate.sql` (tên mặc định `kpah_points`). Nếu panel hosting đã tạo database, điều chỉnh hai dòng `CREATE DATABASE` / `USE` đúng tên trên hosting.
2. User MySQL dùng bởi PHP phải truy cập được cả database game và Point. Nếu cần, cấp riêng quyền `SELECT` trên game và quyền đọc/ghi trên Point theo nghiệp vụ; web hiện còn các chức năng nạp, login, ghi dữ liệu game, nên không giảm quyền user đang dùng khi chưa audit toàn web.
3. Trong file **không commit** `config/register.local.php` thêm vào mảng return:
   ```php
   'points_db' => ['name' => 'kpah_points'],
   ```
   Hoặc cấu hình biến môi trường `KPAH_POINTS_DB_NAME=kpah_points` cho PHP/Apache. Đặt sau khi đã import SQL thành công.
4. Reload PHP/Apache và kiểm tra `/quan-tri/diem-tich-luy`, `/diem-tich-luy`.

## Cài đang chạy (có dữ liệu Point thật) — KHÔNG mất lịch sử

1. Bật maintenance website/tạm dừng webhook cộng Point và đổi quà trong lúc cutover. Sao lưu database chính **cùng tất cả 11 bảng Point và lịch sử PayOS**, xác nhận có thể phục hồi.
2. Tạo schema mới với `database/points_separate.sql`. Script **chỉ tạo bảng rỗng**, không di chuyển row từ nguồn.
3. Trong Navicat export/import **cả cấu trúc lẫn dữ liệu** các bảng `kp_points_*` từ database game sang `kpah_points`, bao gồm ID, khóa, trạng thái đơn, số dư, ledger, `activated_at` trong `kp_points_program`. Với bảng chưa tồn tại ở nguồn, giữ bản rỗng từ SQL cài mới.
4. Trước khi bật config, so sánh ít nhất **COUNT(*) của mỗi bảng**, `SUM(balance),SUM(total_earned),SUM(total_spent)` của `kp_points_wallet`, `COUNT/SUM(points_delta)` của ledger, số đơn theo trạng thái và `kp_points_program.activated_at`. Kiểm tra trên snapshot nhất quán trong thời gian website dừng ghi.
5. Sau khi xác nhận dữ liệu khớp, đặt `KPAH_POINTS_DB_NAME` hoặc `points_db.name`, reload Apache/PHP và kiểm tra User/Admin. Không xóa các bảng Point cũ ngay; giữ backup để rollback. Tránh để hai database tiếp nhận ghi đồng thời.
6. Chỉ mở lại nạp/webhook/đổi quà sau khi test tạo và duyệt đơn trên staging và kiểm tra lịch sử PayOS.

## Truy vấn kiểm tra nhanh (chỉ đọc)

Thay tên `kpah` bằng tên database game nếu khác.

```sql
SELECT 'game' AS source, COUNT(*) AS wallets,
       COALESCE(SUM(balance),0) AS total_balance
FROM kpah.kp_points_wallet
UNION ALL
SELECT 'points', COUNT(*), COALESCE(SUM(balance),0)
FROM kpah_points.kp_points_wallet;

SELECT 'game' AS source, COUNT(*) AS entries,
       COALESCE(SUM(points_delta),0) AS net
FROM kpah.kp_points_ledger
UNION ALL
SELECT 'points', COUNT(*), COALESCE(SUM(points_delta),0)
FROM kpah_points.kp_points_ledger;

SELECT 'game' AS source, activated_at, points_per_1000
FROM kpah.kp_points_program WHERE id=1
UNION ALL
SELECT 'points', activated_at, points_per_1000
FROM kpah_points.kp_points_program WHERE id=1;
```

Các ví dụ trên không tự chuyển/cộng lại Point, không can thiệp trạng thái payOS. Đối chiếu COUNT/SUM không thay thế được backup hoặc kiểm tra chi tiết từng giao dịch.

## Nếu gặp lỗi
- `SQLSTATE[42S02]` / table missing: kiểm tra đã import đủ bảng và giá trị `KPAH_POINTS_DB_NAME`.
- `SQLSTATE[42000]` / access denied: user PHP chưa có quyền trên schema mới.
- Lỗi `missing_payos_ledger`: đây là lỗi đọc báo cáo; kiểm tra quyền SELECT bảng `kp_bank_orders` của game và `kp_points_ledger` trên schema Point mới, log SQL thật rồi xử lý. **Không tự cộng bù hoặc chạy lại webhook.**
- Rollback: tạm dừng toàn bộ ghi, đối chiếu những giao dịch đã phát sinh sau cutover, phục hồi backup/sync trước khi gỡ cấu hình. Chỉ bỏ biến env để quay lại database cũ khi chắc chắn không có row mới bị bỏ lại ở schema Point.

## Kiểm thử
CI chạy trên game schema `kpah_route_test` và schema Point `kpah_route_points_test` riêng; test việc trừ/hoàn Point, đồng bộ giao dịch và metadata, PHP 8.0/8.3, Apache. Điều này không có nghĩa là website production đã được cập nhật database.
