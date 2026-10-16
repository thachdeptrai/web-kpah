# Hồ sơ nhân vật nâng cao & tài nguyên game

## 1. Copy RES từ game

Thư mục `res/` đã được tạo tại **root website** và giữ trong Git bằng `res/.gitignore`.
Nội dung `res/` được ignore; không đẩy toàn bộ tài nguyên game lên repository.

Ví dụ nếu thư mục server chứa `map/`:

```text
Server/
└── map/
    └── icon/
        ├── 176.png
        └── ...
web-kpah/
├── index.php                  (bắt buộc ở root cho XAMPP)
└── res/
    ├── .gitignore
    └── icon/
        ├── 176.png
        └── ...
```

Copy **nội dung bên trong** `map/` vào `web-kpah/res/`. Không copy thành `web-kpah/res/map/`.
Không cần thêm cả thư mục `cMap/` cho chức năng xem icon.

Ví dụ Windows CMD (thay đường dẫn nguồn theo máy bạn):

```bat
robocopy "D:\KPAH\Server\map" "C:\xampp\htdocs\res" /E /R:1 /W:1
```

Dòng trên là ví dụ; xác minh thư mục nguồn trước khi chạy. Không sử dụng tùy chọn `/MIR` vì có thể xóa file ở đích.

**Tuyến truy cập HTTP:** Apache chỉ cho đọc file `/res/icon/{số}.png`, ví dụ `/res/icon/176.png`. File khác bên trong `res/` bị chặn HTTP dù đã được copy. Khi sử dụng Cloudflare, hãy xóa cache của icon nếu thay ảnh với cùng tên.

## 2. Cấu trúc item xác minh từ Java/SQL

- Java `src/data/Database.java` ghi `tob_char.equip`, `inven`, `bag`, `tuido` bằng `item.getInfoSave()` và nối các món bằng `>`.
- `src/real/Item.java` định dạng `getInfoSave() = getDBInfo() + "|" + getAttribute()`.
- CSV trước dấu `|`: vị trí **0** lớp item `clazz`, **1** ID template, **3** độ bền, **4** độ bền tối đa, **5** cấp cộng `plus`, **6** cấp item; các thuộc tính sau dấu `|` được đọc ở dạng index gốc.
- Database `data_item.id` là khóa đối chiếu template, `data_item.name` là tên và `data_item.xstart` được Java tải thành `ItemTemplates.idIcon`.
- Trong `src/data/DataGame.java`, server đọc hình từ `map/icon/{id}.png`. Website đổi tiền tố `map/` thành `res/`.

**Không lấy ID icon trực tiếp từ ID template.** Hai cột `id` và `xstart` có thể khác nhau.

## 3. Trang người chơi

- `/nhan-vat`: danh sách nhân vật; nút **Xem trang bị & túi đồ**.
- `/nhan-vat/chi-tiet?name=TEN_NHAN_VAT`: chỉ hiển thị đồ của nhân vật thuộc tài khoản đã đăng nhập, kiểm tra `tob_web_info_char.userid = team_user.id` và `tob_char.userId = w.userid`.
- 4 mục: **Trang bị**, **Hành trang**, **Rương đồ**, **Túi đồ**.
- Chỉ đọc database; không chuyển, tháo, thay đổi hay cấp vật phẩm.
- Tên, icon, cấp và độ bền lấy từ dữ liệu nguồn. Những thuộc tính chưa đối chiếu tên trong Java được hiển thị là `ATB {index}` để tránh nhầm chỉ số.

### Những trường hợp cần lưu ý

- Nếu ảnh chưa được copy, trình duyệt có thể thấy placeholder hoặc biểu tượng ảnh lỗi; dữ liệu item vẫn hiển thị.
- Item mới được thay đổi khi game còn online có thể chưa được ghi xuống database ngay; trang web phản ánh lần lưu gần nhất.
- Không tự động đọc mọi định dạng khác ngoài PNG từ `res/`. Nếu client sử dụng sprite atlas hoặc file ảnh khác, cần xử lý riêng.
- Không dùng `allitem` hoặc các chuỗi dữ liệu nội bộ khác cho túi đồ nếu chưa đối chiếu lại cơ chế lưu của Java hiện tại.

## 4. Kiểm thử

```bat
C:\xampp\php\php.exe -l app\pages\character-detail.php
C:\xampp\php\php.exe tests\character_inventory_smoke.php
```

Truy cập `/nhan-vat/chi-tiet?name=...` khi đã đăng nhập và thử bằng tên một nhân vật thuộc **tài khoản khác**: không được trả về hành trang.

Không cần migration database. Không thêm tài nguyên `res/` vào Git.
