# JavaScript công khai và bảo vệ dữ liệu API

## Phạm vi thực tế

**JavaScript gửi xuống trình duyệt không thể giữ bí mật.** Người dùng có thể mở DevTools, xem response và chương trình thực thi, kể cả sau khi mã bị minify/obfuscate. Không đặt mật khẩu, `private_key`, token thanh toán hoặc quy tắc cấp Point/item đáng tin cậy trong JS. Repo GitHub hiện công khai nên source vẫn có thể đọc từ GitHub.

**Payload API qua HTTPS đã được mã hóa trên đường truyền bằng TLS.** Phần JSON trong DevTools là dữ liệu được chính trình duyệt đã xác thực giải mã; thêm AES với khóa nhúng cùng JS không giấu được dữ liệu và có thể phá luồng client/API. Dữ liệu mà người dùng không có quyền xem phải bị chặn ngay ở PHP/database, không chỉ che JSON.

## JS phiên bản production (đã có)

- Source dễ bảo trì: `assets/js/*.js`; vẫn dùng trong các unit test.
- Build đã minify: `assets/js-dist/*.js`, **không tạo source map**.
- Workflow `.github/workflows/build-public-js.yml` dùng **Terser 5.44.1**, chạy khi file JS/builder thay đổi, kiểm tra syntax và các test Node hiện tại trước khi commit bản build lên `main`. Nếu không push được do quyền token/thay đổi đồng thời, không force-push và phải xem CI.
- Apache `.htaccess` tự chọn `assets/js-dist/<name>.js` khi có bản build, trong khi URL trình duyệt giữ `/assets/js/<name>.js`. Nếu không có build thì dùng bản source để tránh hỏng giao diện; điều đó **không phải bảo vệ truy cập**.
- Chặn đường dẫn source map; thêm `nosniff`, `Cross-Origin-Resource-Policy: same-origin`, `Referrer-Policy: no-referrer` cho static JavaScript khi `mod_headers` được bật.
- Không minify tại PHP request; tránh tăng tải và dependency mới trên VPS Windows Server.

## API response

`kp_send_security_headers(false)` áp dụng cho response phi HTML:

- `Cache-Control: private, no-store, no-cache` và `Vary: Cookie`: tránh cache sai dữ liệu giữa các phiên.
- `X-Content-Type-Options: nosniff`, `Cross-Origin-Resource-Policy: same-origin` và CSP chặn nhúng/tải script theo ngữ cảnh không phải HTML.
- `X-Robots-Tag: noindex, nofollow, noarchive` và `Referrer-Policy: no-referrer`.

Những header này **không mã hóa JSON**. Chúng là cơ chế chống cache/lộ dữ liệu ngoài ý muốn. Phân quyền dữ liệu, kiểm tra session/CSRF, giới hạn request, idempotency và xác minh chữ ký/thông tin nhà cung cấp thanh toán vẫn được thực hiện trên backend. Callback payOS/thẻ phải giữ đúng payload/chữ ký gốc của provider; không tự thay giao thức bằng AES.

## Kiểm thử và triển khai

1. GitHub Actions: xem kết quả **Build and publish minified browser JavaScript**, **Security regression checks**, **PHP and JS syntax checks**, và **Routes and account regression**.
2. Trên Apache/XAMPP cần bật `mod_rewrite`, `AllowOverride` và (để có static headers) `mod_headers`. Khi pull nên lấy commit build JS mới nhất cùng bản sửa Apache; có thể kiểm tra trực tiếp nội dung minified và tương tác trang.
3. Truy cập URL /assets/js/ui-system.js trả về bản minify nếu `assets/js-dist/ui-system.js` có trên VPS. File vẫn có thể bị xem/đọc bởi người dùng, đó là giới hạn của trình duyệt.
4. Bảo đảm HTTPS tới **cả người dùng và origin**, khuyến nghị Cloudflare **Full (strict)**. Không ép `HTTPS`/forwarded-proto theo request khi chưa xác định proxy tin cậy, để tránh vòng lặp redirect hoặc tin header giả mạo.
5. Kiểm thử PHP offline: `php tests/client_api_transport_security_smoke.php`; kiểm tra response thực từ staging vì PHP built-in server không thực thi `.htaccess`.

Nếu thật sự cần **mã hóa tầng ứng dụng** cho giao tiếp server-to-server, phải thiết kế giao thức mới với khóa bí mật nằm trên **hai server**, dùng authenticated encryption (AES-GCM), version, nonce chống replay, xác thực đối tác và kiểm thử staging. Không áp dụng cho payload ngân hàng/game hiện tại khi chưa có hợp đồng API tương thích.
