# Game server patch: Giftcode item options

Website chỉ được bật lưu token `DBO` sau khi game server đã áp patch
`giftcode-item-options.patch` và restart thành công. Patch dựa trên source Java
được audit cùng task này:

- `src/data/Database.java`
- `src/real/plugins/GiftCode.java`

Contract và giới hạn nằm tại
[`docs/GIFTCODE_ITEM_OPTIONS_AUDIT.md`](../docs/GIFTCODE_ITEM_OPTIONS_AUDIT.md).

Quy trình staging:

1. Backup source và database.
2. Áp `database/giftcode_item_options.sql`.
3. Áp patch Java tại root project game server.
4. Build/restart staging.
5. Tạo một code có một `DBO`, nhận bằng nhân vật test, relog và kiểm tra item
   vẫn giữ đúng option/param.
6. Chỉ sau bước 5 mới phát code thật.

Patch không thay đổi token `DB`; Giftcode cũ tiếp tục đi qua nhánh cũ.
