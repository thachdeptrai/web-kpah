# Cấu hình nạp thẻ và chiết khấu

Website có luồng gửi thẻ `/nap-the`, callback `/api/card-callback` và kiểm tra trạng thái qua API đối tác đã có trong mã nguồn.

## Chưa mở thẻ thật theo mặc định

Chỉ mở khi đầy đủ:

- `KPAH_CARD_PARTNER_ID`: partner ID từ đối tác
- `KPAH_CARD_PARTNER_KEY`: khóa đối tác
- `KPAH_TOPUP_SIGNING_SECRET`: khóa HMAC riêng dài tối thiểu 32 ký tự; giữ bí mật
- `KPAH_CARD_DISCOUNT_PERCENT`: phần trăm chiết khấu của nhà cung cấp, số nguyên từ **0 đến 99**, bắt buộc khai báo để mở gửi thẻ
- `KPAH_TOPUP_GAME_QUEUE_VERIFIED=1`: chỉ đặt sau khi xác thực game server xử lý `board_naptien` chính xác
- `KPAH_TOPUP_SERVER_ID`: `svID` đã xác minh phù hợp game server.

Ví dụ **chỉ để giải thích**, thẻ mệnh giá 100.000đ, chiết khấu 20%: giá trị quy đổi 80.000đ. Nếu tỷ lệ 100 Lượng/10.000đ, dự kiến nhận 800 Lượng. Đây không phải mức chiết khấu thực tế và không được tự động đặt trên production.

Cấu hình biến môi trường trên Apache/XAMPP theo cách triển khai đã chọn, không commit khóa/mật khẩu vào repo. Nếu thay đổi tỷ lệ chiết khấu, các đơn mới lưu ảnh chụp `discount_percent` và `net_amount` riêng; đơn cũ không bị tính lại.

## Bảo vệ giao dịch

- Phải gửi đúng mệnh giá in trên thẻ, nhà mạng, PIN và serial.
- Không lưu PIN/serial dạng rõ trong JSON.
- Callback không được tự cấp tài nguyên: phải tra cứu lại HTTPS status với đối tác.
- Đối chiếu `value` trả từ API với **mệnh giá in trên thẻ** (`amount`), **không so sánh** với `net_amount`.
- Chỉ cấp số `units` được tính từ `net_amount` đã khóa lúc gửi.
- Không tự phát lại lệnh cộng vào bảng MyISAM khi trạng thái `crediting`/đối soát chưa rõ; tránh cộng hai lần.

## Cần xác nhận trước khi nạp tiền thật

1. API/partner ID thực tế và chính sách chiết khấu theo từng loại thẻ (hiện một tỷ lệ chung).
2. Tài khoản game-server có đọc `board_naptien` và tiêu thụ hàng chờ đúng.
3. Test thẻ sandbox/giá trị nhỏ, callback lặp, callback sai chữ ký, mệnh giá sai, mất kết nối và lịch sử giao dịch.
4. Apache rewrite, PHP cURL TLS và quyền ghi của `storage/topup` trên VPS Windows.

Không dùng thẻ thật khi chưa đáp ứng tất cả điều kiện trên.
