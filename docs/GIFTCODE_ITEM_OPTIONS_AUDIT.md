# Audit Giftcode item/options

Audit này đối chiếu source Java do chủ dự án cung cấp với schema `kpah.sql` và
website `web-kpah`. Mục tiêu là chốt một contract có thể được cả Admin và game
server đọc giống nhau, không tự chế JSON trong cột `giftcode.item`.

## Luồng hiện tại

1. `app/admin/giftcodes.php` ghi phần thưởng vào `giftcode.item`.
2. `data.Database#newDoGetGiftCode` đọc bản ghi `giftcode`, tách các phần thưởng
   bằng dấu phẩy và tách trường bằng dấu hai chấm.
3. Token trang bị hiện tại là `DB:<data_item.id>:<quantity>:<minutes>`.
4. Mỗi lần lặp gọi `real.plugins.GiftCode.AddItem(...)`.
5. `AddItem` lấy template gốc từ `Map.itemTemplates.get(5)`, tạo `Item`, sinh
   chỉ số mặc định/đặc biệt và thêm item vào `player.iItems`.
6. `Item#getAttribute()` lưu toàn bộ chỉ số theo thứ tự cố định; khi tải lại,
   `Char#initInfoFromDB` giải mã cùng thứ tự đó.

Giftcode cũ chỉ truyền ID, số lượng và thời hạn. Parser hiện tại **không có
trường option tùy chỉnh**.

## Cấu trúc option thật của KPAH

Tên option được server đọc từ `data_attribute ORDER BY id`. Dump đã kiểm tra có
141 ID liên tục từ 0 đến 140. Giá trị item được lưu trong năm vùng:

| Option ID | Mảng Java | Kiểu lưu | Ghi chú |
| --- | --- | --- | --- |
| 0–32 | `atb[33]` | `short` | 0–9 là chỉ số template `data_item.atb0..atb9` |
| 33–42 | `newAtb[10]` | `byte` | ID 33–34 được client hiển thị theo đơn vị `param * 1000` |
| 43–57 | `addMoreLevelSkill[15]` | `byte` | Cấp kỹ năng cộng thêm |
| 58–60 | `lockAtb[3]` | `byte` | Chỉ số khóa |
| 61–140 | `otherAtt[80]` | `short` | Chỉ số mở rộng |

Các option mặc định được lấy từ `data_item`; option Admin chọn được áp dụng sau
khi item hoàn tất khởi tạo đặc biệt để giá trị tùy chỉnh không bị hàm tạo ghi
đè. Website không được tạo option ngoài `data_attribute`.

## Hình ảnh

- Trang bị `data_item`: source Admin Java dùng `map/icon/<xstart>.png`.
- Potion `data_potion`: source dùng `map/images_potion/<idImage>.png`.
- Gem: source dùng `map/images_gem/<GemTemplate.idImage>.png`, nhưng danh sách
  `GemTemplate` được hardcode trong Java chứ không có bảng chuẩn trong dump.

Website chỉ đổi thư mục gốc `map/` thành `res/`, giữ nguyên phần còn lại. PHP
phải kiểm tra file tồn tại trước khi trả URL; giao diện dùng placeholder nếu tài
nguyên chưa được đồng bộ. Phiên bản đầu của bộ chọn chỉ đưa `data_item` từ DB
vào catalog, không hardcode GemTemplate.

## Contract tương thích ngược

- Mặc định/legacy: `DB:<itemId>:<quantity>:<minutes>`
- Có tùy chỉnh: `DBO:<itemId>:<quantity>:<minutes>:<lock>:<magic>:<options>`
- `options`: các cặp `optionId=param` phân cách bằng `;`.

Ví dụ contract (ID chỉ mang tính minh họa cú pháp):

```text
DBO:12:1:10080:1:0:0=500;33=1
```

Không dùng JSON vì parser game tách token theo dấu phẩy/dấu hai chấm. `DBO` là
prefix mới nên không thay đổi cách đọc `DB`, `PT`, `GEM` và các token đặc biệt
đang tồn tại.

## Giới hạn và migration

`giftcode.item` trong dump là `VARCHAR(255)`. Một danh sách nhiều item/options
có thể vượt 255 byte, nên cần migration đổi riêng cột này thành `TEXT`. Đây là
thay đổi mở rộng dung lượng, tương thích với toàn bộ dữ liệu cũ. Website sẽ đọc
kiểu cột thực tế và từ chối lưu chuỗi vượt capacity nếu migration chưa chạy.

`giftcode.expire` tồn tại nhưng source `newDoGetGiftCode` không đọc cột này;
không được quảng cáo thời hạn Giftcode là đang hoạt động. Trường `minutes` trong
token là thời hạn **vật phẩm**, được `Item.timeLoan/minuteExist` xử lý.

## Phạm vi kiểm thử khả dụng

Có thể kiểm thử offline việc query catalog, validate ID/param, serialize/parse
round-trip và giữ token legacy. Không có runtime game đầy đủ, map/res assets hay
database staging trong workspace, vì vậy nhận thưởng trong client thật phải
được xác nhận sau khi áp patch Java và migration lên server thử nghiệm.
