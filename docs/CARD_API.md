# Nạp thẻ tự động KPAH — TheCaoSieuToc

Nhà cung cấp: https://thecaosieutoc.vn/docs

## Thiết lập XAMPP

1. Trong tài khoản TheCaoSieuToc, vào mục Merchant / Kết nối API đổi thẻ để lấy **Partner ID** và **Partner Key**. **API Token của mục rút/nạp ví hoặc mua thẻ KHÔNG dùng được để gửi thẻ đổi tiền**.
2. Sao chép `config/topup.local.sample.php` thành `config/topup.local.php` tại VPS và điền `partner_id`, `partner_key`. Không gửi lên GitHub, không đưa vào đường dẫn public.
3. Ở cấu hình callback của Merchant: `https://nghienkpah.io.vn/api/card-callback` (GET/POST).
4. Kiểm tra game server thực sự tiêu thụ `board_naptien` theo đúng `username`, `svID` và đơn vị Lượng. Chỉ sau khi thử nghiệm thành công, chỉnh `game_queue_verified => true` và `server_id` đúng cấu hình. **Không bật cờ này khi chưa xác minh**.
5. Xác nhận PHP đã bật extension `curl` và `openssl`, HTTPS của nhà cung cấp hoạt động, thư mục `storage/topup` có quyền ghi.

## Tiền, chiết khấu và trạng thái

- `GET https://thecaosieutoc.vn/chargingws/v2/getfee?partner_id=...` trả phí `fees` theo nhà mạng và mệnh giá. Form truy vấn qua `/api/card-fees` để hiển thị ước tính.
- `POST https://thecaosieutoc.vn/chargingws/v2` với `command=charging`. Chữ ký `md5(partner_key + code + serial)`.
- `command=check` tra cứu lại thẻ bằng PIN + serial + nhà mạng. Vì vậy hệ thống lưu bộ đôi này **được mã hóa AES-256-GCM** tại `storage/topup` để phục vụ đối soát, không lưu dạng rõ; khóa được dẫn xuất ổn định từ Partner Key. Thay Partner Key có thể làm mất khả năng giải mã đơn cũ — đối soát hết giao dịch chờ trước khi đổi khóa.
- Chỉ khi `status=1`, `value` trùng mệnh giá đã khai báo, hệ thống mới lấy `amount` (VNĐ **thực nhận sau chiết khấu**) làm cơ sở quy đổi Lượng.
- `status=2`: sai mệnh giá, giữ để đối soát; `status=3`: thẻ lỗi; `status=99`: đang chờ.
- `board_naptien` là MyISAM: ghi trạng thái `crediting` trước khi chèn, không tự chèn lại khi kết quả chèn không rõ để tránh cộng hai lần.
- Khoản chiết khấu tự động từ nhà cung cấp, không còn `KPAH_CARD_DISCOUNT_PERCENT`.

## Tránh lỗi khi triển khai

Nạp thẻ thật bị tắt theo mặc định nếu thiếu tài khoản Merchant hoặc chưa xác nhận game server cấp tài nguyên. Thử giao dịch mệnh giá thấp trước khi mở cho mọi người; kiểm tra callback đúng/sai chữ ký, callback lặp, mất mạng, phí thay đổi và đơn `needs_review`. Mã PIN, serial và Partner Key không được đưa vào log, URL hoặc screenshot.

Đây là cấu hình API và logic đối soát trên repo, chưa có xác nhận giao dịch thật với thông tin Merchant của bạn.
