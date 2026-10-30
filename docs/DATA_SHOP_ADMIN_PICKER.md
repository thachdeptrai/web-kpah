# Bộ chọn Data Shop cho Admin Giftcode và Point

## Đối chiếu source Java đã cung cấp

- `data.Database.loadShopTemplate()` đọc `data_shop` theo `id,idImg,name,decript,value,price,type,tab,sell,priceSale` vào `real.ShopTemplate`.
- `data_item` lại được đọc thành `ItemTemplates`. Cùng một ID số có thể có nghĩa khác nhau ở hai bảng. Không JOIN/gộp ID hoặc dùng `DB:<shop_id>:...` để tạo ShopTemplate.
- `data.Database.newDoGetGiftCode()` trong source Java tải ở đây có token `DB` (từ `data_item`) và `ITEM` (vẫn tạo `ItemTemplates`), **không có parser `SHOP`**.
- `real.Char.doAddSpecialItem(id,qty)` nhận `ShopTemplate` nhưng dùng mảng `listSpItem`. Trong source đính kèm, constructor khởi tạo `new int[2]`. Đưa ID Data Shop lớn vào hàm này trực tiếp có nguy cơ `ArrayIndexOutOfBoundsException`; cần audit khởi tạo, persistence và packet inventory trên game server trước khi phát thực tế.

## Giao diện

- Giftcode Admin: tab **Trang bị · data_item** và **Vật phẩm Shop · data_shop**, hỗ trợ tìm tên/ID, bộ lọc loại, phân trang, hiện icon khi asset khớp `res/icon/<id>.png`. Cho chọn thử SHOP vào draft để cấu hình số lượng, nhưng **không cho lưu Giftcode SHOP** mặc định.
- Point Admin: tab **Trang bị · data_item**, **Data Shop** và **Options**; khi chọn Data Shop sẽ lưu `item_source=data_shop` bên cạnh `item_id`, icon và thông tin mô tả. Quà Point chỉ lưu metadata, **Admin vẫn phải trao quà thủ công**.
- `data_shop` là bảng riêng từ SQL game, không cần sao chép vào `data_item` hoặc `kpah_points`. Trang web đọc catalog từ kết nối SQL game hiện có.

## SQL cần chạy (chỉ cho quà Point Data Shop)

Chạy `database/points_stage8_shop_source.sql` trên cùng MariaDB sau khi đã có `kpah_points.kp_points_reward_display`. Migration chỉ thêm cột `item_source` (mặc định `data_item`), giữ nguyên quà cũ. Sao lưu trước.

Nếu chưa chạy migration, quà `data_item` cũ tiếp tục lưu bình thường; quà `data_shop` được backend chặn với thông báo cần chạy SQL.

## Giftcode SHOP — an toàn khi triển khai

Mã web chuẩn bị contract có phân biệt nguồn riêng `SHOP:<shopId>:<quantity>`; parser PHP nhận lại chuỗi SHOP khi game server đã hỗ trợ. **Đừng bật ngay**: Java trong src.rar không hiểu token này. `config/register.php` mặc định `giftcode.shop_enabled=false`, backend sẽ chặn POST chứa ShopTemplate và không sinh Giftcode không thể nhận.

Chỉ thiết lập `giftcode.shop_enabled=true` sau khi sửa Java server để hỗ trợ ShopTemplate an toàn và đã test nhận quà, lưu/relogin/đổi map, nhiều bản ghi cùng ID, cập nhật inventory, ID quá giới hạn và rollback khi kho đầy. Không ép `SHOP` sang `DB`/ `ITEM`; không ghi đè dữ liệu `data_item`.

## Test

- `tests/data_shop_picker_smoke.php`: nguồn ID trùng, SHOP feature-gate, từ chối tùy chỉnh option không tương thích, legacy `DB` không đổi.
- `tests/points_picker_ui.test.js`: tab nguồn riêng và source lưu, loại bỏ option cũ khi chọn ShopTemplate.
- Các CI PHP/JS/Point cần qua trước triển khai; CI chỉ test dữ liệu giả, không xác nhận bộ game server live đã hỗ trợ `SHOP`.
