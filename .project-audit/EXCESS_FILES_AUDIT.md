# Rà soát file thừa — 2026-10-27

Trạng thái: **đề xuất, chưa xóa**. Root C:/xampp/htdocs, HEAD 93cf1826fdb7c08f72c75437074edb94ee5a89a6.

## Ứng viên dọn (xếp theo độ tin cậy)

1. **Có thể dọn sau khi xác nhận giá trị vận hành:** `res/images_potion/New folder/` (122 file). Có 28 file trùng byte/hash với file **bên ngoài** thư mục này. Dấu hiệu thư mục copy từ nguồn ảnh; chưa chứng minh mọi 122 file là thừa. Cần so đường dẫn được tham chiếu trong code, dữ liệu `data_potion` và giao diện trước khi xóa.
2. **Ứng viên bản copy:** `res/images_potion/* - Copy.png` (ít nhất 7 tên) và tên copy trong `New folder`. Một số trùng hash với tài nguyên khác. Không xóa icon mang ID chuẩn khi chưa kiểm tra dữ liệu game.
3. **Kiểm tra tay:** `res/check imei.txt`, `res/help.txt`, `res/Untitled-3.png`. Tên gợi ý tài liệu/ảnh để tạm nhưng không đủ chứng cứ xóa; nội dung và nguồn sử dụng chưa đánh giá.
4. **Runtime nên quản lý retention, không xóa tùy tiện:** `storage/ratelimit/` (221 file trong inventory), `storage/topup/` (150 file) và `storage/giftcode/`. Đây là trạng thái vận hành, có thể dùng cho chống spam, giao dịch, đối soát; không coi là file thừa.
5. **Chắc chắn phải giữ:** `storage/game-downloads/` chứa IPA (~18.23 MB), APK (~8.29 MB), JAR (~4.04 MB); `catalog.json` tham chiếu đúng các tên file này. `assets/js-dist/` là JS minified phát qua Apache; `assets/fonts/DejaVuSans-Bold.ttf` được captcha.php sử dụng; `res/icon/` được render theo ID từ DB.
6. **Không tự phục hồi/xóa thêm:** 3 file Git tracked đã bị xóa trước audit: `game-server-patch/README.md`, `game-server-patch/giftcode-item-options.patch`, `scripts/import-approved-auth-webp.ps1`. Cần làm rõ đó là dọn có chủ ý hay không.
7. **Không đụng đến:** `config.register.local.php`, `config/payos.local.php`, `config/topup.local.php`, mọi SQL thật, khóa, dữ liệu nạp, nhật ký, `.htaccess` và `.git`.

## Mức độ chứng minh

Inventory có 5.568 file, 33 Markdown, 0 scan errors, hai biên bị bỏ qua là `.git` và `.project-audit`. Tìm kiếm tham chiếu chỉ có giá trị sàng lọc: asset có thể được dùng bằng đường dẫn tạo động, dữ liệu DB hay game client ngoài repository. Không có ứng viên nào được chứng minh 100% an toàn để xóa.

## Bước xác minh tiếp theo

- Truy vết mọi `res/images_potion` qua template, API, SQL `data_potion`, build và server/game client.
- So sánh file duplicate theo SHA-256 rồi so tên/ID và consumer, thử thay thế trên bản sao môi trường.
- Đo dung lượng và giá trị thật của nhóm runtime trong chính sách retention.
- Sao lưu và tạo manifest trước mọi thao tác dọn; không xóa trong nhiệm vụ audit.
## Dọn dẹp có thể hoàn tác — 2026-10-27
- Đã chuyển 28 file từ `res/images_potion/New folder/` sang `C:/xampp/kpah-cleanup-quarantine/2026-10-27-potion-duplicates/`.
- Mỗi file trùng SHA-256 với một file đang được giữ lại trong `res/images_potion/` và có bản ghi nguồn/đích/hash trong `manifest.json` ngoài webroot.
- Kiểm tra sau chuyển: 28/28 hash hợp lệ; 94 file còn trong `New folder/`; tổng dung lượng cách ly 33.423 bytes.
- Chưa có runtime/gameplay test. Nếu có consumer dùng đúng đường dẫn cũ thì khôi phục theo manifest.
- Không xóa các file runtime `storage/`, `res/icon`, file tải game, và không sửa các thay đổi Git có sẵn.
