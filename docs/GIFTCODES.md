# Giftcode trên website NGHIỆN KPAH

## Nguồn dữ liệu thực tế

- Bảng game `giftcode`: `id`, `giftcode`, `xu`, `luong`, `luongLock`, `item`, `expire`, `limit_use`, `type`.
- Bảng game `giftcode_log`: `id`, `giftcode`, `player`, `item`, `xu`, `luong`, `luongK`.
- Không thay đổi schema game. `type=0` và `limit_use>0` là những điều kiện hiện được dùng trong mã Java đã cung cấp. **Trường `expire` không được mã Java kiểm tra** trong nhánh nhận code đang được sử dụng, nên UI không hứa hẹn ngày hết hạn.
- Mã mới không tự xuất hiện trên trang chủ. Admin phải tích **Hiển thị trên trang chủ**; metadata lưu tại `storage/giftcode/catalog.json`.
- Công cụ quản trị nằm tại `/quan-tri/giftcode`, chỉ role `admin` truy cập; role `key` bị từ chối. Phiên quản trị, CSRF, bộ giới hạn số thao tác dùng guard hiện có.
- Chức năng `Sao chép` trên trang chủ chỉ copy mã. Người chơi vào game để nhận quà. Website không trực tiếp cập nhật ví hay cộng tài nguyên.

## Vận hành

1. Sao lưu DB và `storage/` trước khi triển khai.
2. Cho phép PHP ghi `storage/giftcode` (thư mục web server chặn HTTP trực tiếp). Không cần chạy migration SQL.
3. Tạo giftcode, nhập Xu/Lượng/Lượng khóa và số lượt ban đầu. Chỉ dùng item khi đã xác minh cú pháp vật phẩm với server Java.
4. Tích hiển thị nếu muốn công khai code, chọn **Tạm khóa** nếu chưa cho người chơi nhập.
5. Sau khi lưu, mở trang chủ và kiểm tra danh sách. Mã mới chỉ hiển thị nếu `type = 0`, `limit_use > 0` và admin đã công khai.
6. Tại trang quản trị, lọc lịch sử theo code hoặc tên nhân vật. Bảng `giftcode_log` không có timestamp; ID chỉ phản ánh thứ tự ghi tương đối.
7. **Xóa:** code chưa ghi nhận sử dụng bị xóa hẳn; code có lịch sử được khóa và ẩn để giữ truy vết.

## Giới hạn và bảo mật

- Không thay đổi chuỗi giftcode sau khi tạo vì lịch sử đang lưu tên code dạng text, không phải khóa ngoại.
- Server Java được cung cấp dùng SQL nối chuỗi và xử lý ghi log/cập nhật limit riêng, nên cần harden server game trước khi có thể bảo đảm chống race-condition/SQL injection.
- `expire` của bảng game không đồng nghĩa với lịch công khai. Không hiển thị ngày hết hạn giả.
- Dữ liệu metadata công khai bị giới hạn kích thước và HTML luôn escape. Không đưa tên người chơi trong lịch sử ra trang công khai.
- **Backup cả `storage/giftcode/catalog.json`** khi chuyển VPS vì file này tách khỏi Git. Nếu file chưa tồn tại, trang chủ hiển thị trạng thái chưa mở giftcode.
