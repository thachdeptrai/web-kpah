# Kiểm thử refactor URL và thư mục

## Các lớp kiểm tra

| Lớp | Nội dung |
| --- | --- |
| PHP lint | Mọi file PHP trong app, config, tests, tools và index |
| Smoke hiện có | Quy đổi nạp, trạng thái chờ cấp, TOTP, CSRF, quyền admin, BIGINT, whitelist xếp hạng |
| Route/session | Handler tồn tại, alias hợp lệ, scope admin/player độc lập khi SCRIPT_NAME là index.php |
| HTTP | URL cũ/mới, query string, slash cuối, POST không redirect, 404, chặn file nội bộ, asset, CAPTCHA |
| Tài khoản | Đăng nhập, cập nhật điện thoại, đổi mật khẩu, xác minh email bắt buộc, đăng xuất và tách session |
| Schema | Chín truy vấn xếp hạng và truy vấn nhân vật chạy trên schema lấy từ SQL được cung cấp |
| Quyền dữ liệu | Hai người chơi có id/userid khác nhau; chỉ thấy nhân vật thuộc tài khoản của mình |
| Đăng ký | Với SMTP được thay bằng fixture OTP: OTP gắn email, mã giới thiệu sai, tạo tài khoản và đăng nhập mới |
| Callback | Phương thức không hợp lệ, nhà cung cấp chưa bật, endpoint cũ giữ nguyên POST |

`tests/fixtures/schema.sql` chỉ chứa DDL, không có dữ liệu người chơi. Dữ liệu test được tạo bằng `tests/seed_fixture.php`; script từ chối chạy trừ khi database tên chính xác `kpah_route_test`. Chỉ chạy trên database bỏ được. Không dùng tài khoản, mật khẩu hoặc thẻ nạp thật.

## Chạy bộ smoke

```sh
for test in tests/*_smoke.php; do php "$test" || exit 1; done
```

## Chạy integration

1. Tạo database mới, rỗng `kpah_route_test` trên MySQL/MariaDB riêng.
2. Thiết lập `KPAH_DB_HOST`, `KPAH_DB_PORT`, `KPAH_DB_NAME`, `KPAH_DB_USER`, `KPAH_DB_PASS`; đặt `KPAH_ADMIN_REQUIRE_TOTP=0` **chỉ trong môi trường test**. TOTP vẫn có bộ known-answer riêng.
3. `php tests/seed_fixture.php`.
4. `php -S 127.0.0.1:8089 -t . tests/dev_router.php`.
5. `KPAH_TEST_URL=http://127.0.0.1:8089 python3 tests/http_regression.py`.

Bật `KPAH_TEST_SESSION_FIXTURE=1` để thêm test đăng ký bằng OTP mô phỏng. CLI test và HTTP server cần cùng PHP/session.save_path và cùng user hệ điều hành. `fixture_session.php` chỉ chạy CLI và chỉ với database test; không có URL công khai.

Workflow `routing-regression.yml` chạy PHP 8.0/8.3 trên CLI server và thêm Apache 2.4/PHP 8.3 với `.htaccess` thật, MariaDB 10.4. Máy kiểm thử cục bộ đã chạy PHP 8.3/MariaDB 10.11; việc chạy Apache cục bộ bị giới hạn quyền đổi user/group, nên kết quả CLI không được xem là chứng minh rewrite Apache.

## Những việc cần kiểm tra trên staging

- Gửi/nhận email qua SMTP thật, khả năng thư vào inbox/spam.
- Nhà cung cấp thẻ thật: ký callback, đối soát, cấp trùng và game server nhận tài nguyên.
- Cấu hình riêng của XAMPP/Cloudflare, HTTPS, OPcache và quyền thư mục trên Windows.

Các test mô phỏng không xác nhận giao dịch tiền thật hoặc email thật. Không có bộ test hữu hạn nào cam kết tuyệt đối không phát sinh lỗi.

## Kết quả kiểm tra cục bộ

- 64 file PHP qua kiểm tra cú pháp; 6 bộ smoke test đều qua.
- 258 kiểm tra HTTP qua trên PHP 8.3.6/MariaDB 10.11, gồm 6 kiểm tra đăng ký với OTP mô phỏng.
- Chín truy vấn xếp hạng và liên kết nhân vật chạy trên cấu trúc SQL đã cung cấp.
- Chưa chạy được Apache thực tế tại máy kiểm thử do giới hạn đổi user/group; workflow CI đã chuẩn bị nhưng chưa chạy vì push cần xác nhận.
- Chưa xác nhận giao diện bằng trình duyệt: runtime thiếu Chromium và tải bộ cài không thành công. Kiểm tra asset HTTP đã qua; không coi đó là kiểm tra responsive.
