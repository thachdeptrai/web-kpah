# Quà Điểm nông dân khi nạp ngân hàng

## Thiết lập và hành vi

Admin mở **/quan-tri/ty-le**, kéo tới mục **Thưởng Điểm nông dân khi chuyển khoản**. Nhập `Điểm nông dân / mỗi 10.000đ nạp` (mặc định **10.000**) và tích **Bật quà Điểm nông dân**, rồi lưu. Đây là một chương trình **khác** với Point của website, cũng khác với **% thưởng VNĐ ngân hàng** đã có trong cùng trang admin.

- Nạp **10.000 VNĐ** nhận **10.000 Điểm nông dân**.
- Nạp **50.000 VNĐ** nhận **50.000 Điểm nông dân**.
- Nạp **100.000 VNĐ** nhận **100.000 Điểm nông dân**.

Tỷ lệ được lấy theo mệnh giá **VNĐ thực chuyển qua payOS**, **không nhân với % VNĐ thưởng**. Ví VNĐ và `tongnap` vẫn xử lý bằng logic cũ. Chỉ các QR được **tạo mới sau khi bật** mới nhận phiếu quà; bật lên không cấp ngược cho đơn cũ. Khi admin tắt, phiếu quà đã được tạo **không biến mất**.

Mặc định chương trình Điểm nông dân **tắt** tới khi admin quyết định bật trên website. Tệp cấu hình: `storage/topup/bank-farmer.json`, riêng biệt với `storage/topup/bank-bonus.json` (thưởng % VNĐ).

## Nhận quà trên website

1. Người chơi tạo QR tại **/nap-ngan-hang** sau khi chương trình được bật.
2. payOS xác nhận tiền thật; website hoàn tất cộng ví VNĐ và ghi nhật ký giao dịch.
3. Sau khi **thoát game hoàn toàn** trên nhân vật nhận, người chơi vào mục **Nhận Điểm nông dân** ở trang Nạp ngân hàng, chọn nhân vật thuộc tài khoản, tích xác nhận thoát game và bấm **Nhận điểm**.
4. Điểm cộng trực tiếp vào `tob_char.pInfo` phần tử 117 (số nguyên Java 32-bit) của đúng nhân vật. Nếu đã nhận, không thể nhận lại.

Do một tài khoản có thể có nhiều nhân vật, **không tự ý cộng cho nhân vật đầu tiên**. Website cũng không thể xác nhận hoàn toàn trạng thái đang online của Java server, vì vậy yêu cầu người chơi thoát game là bắt buộc; nếu game còn giữ dữ liệu trong RAM và tự ghi đè, có thể làm mất phần điểm vừa nhận. Trong trường hợp đó phải đối soát trên server, không cấp thêm tự động.

## Bảo vệ giao dịch và xử lý sự cố

- Tạo phiếu có `order_code`, `user_id`, `amount`, `points` **trước khi gọi API payOS tạo QR**. Lưu trong `storage/topup/bank-farmer/<order_code>.json`.
- Muốn nhận phải có `kp_bank_orders.status=completed`, mã order và user khớp, marker `VND_BONUS` hợp lệ, và nhật ký ví payOS `status=completed` với cùng user và tiền nạp.
- Phần thưởng không được cấp chỉ dựa vào redirect trình duyệt hay callback tự khai.
- Có khóa riêng cho mỗi order. Ghi trạng thái `prepared` bền vững **trước** khi cập nhật bảng MyISAM, sau đó mới ghi `completed`.
- Câu `UPDATE tob_char ... WHERE pInfo=?` kiểm tra nguyên dữ liệu gốc để phát hiện thay đổi đồng thời. Không ghi lại hay tính lại tự động khi DB/ghi nhật ký trả kết quả bất định.
- Phiếu còn ở `prepared` hay `needs_review` phải kiểm tra thủ công số điểm thực tế, `points_before`, `points_after`, `char_id`, order payOS và trạng thái đăng nhập game trước khi đưa ra quyết định.
- **Tuyệt đối không xóa thủ công phiếu quà rồi bấm nhận lại**, vì bảng MyISAM không rollback.
- Không có thay đổi schema hoặc bảng SQL mới; không chạm vào Điểm nông dân của những người chơi không nhận quà.
- Nếu php.ini thiếu quyền ghi `storage/topup` thì **đừng bật khuyến mãi**. Backup cả `storage/topup` và database cùng lúc.

## Kiểm thử

```bat
C:\xampp\php\php.exe tests\bank_farmer_smoke.php
```

Kiểm thử độc lập kiểm tra: mặc định tắt, tỷ lệ 10.000/10.000, mốc tiền lớn hơn, khóa tạo phiếu trùng, thay đổi cấu hình admin, và sửa đúng `pInfo[117]` mà không thay đổi các trường khác. CI chạy trên PHP 8.0.

Chưa có kiểm thử thanh toán thật hoặc nhận quà trên một game server online; nên thử trên staging với nhân vật phụ đã thoát game, ví và bảng nhân vật được sao lưu trước khi bật cho toàn bộ người chơi.
