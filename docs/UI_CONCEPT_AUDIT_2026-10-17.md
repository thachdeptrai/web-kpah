# NGHIỆN KPAH — UI audit so với concept fantasy/anime (17/10/2026)

- **Nguồn:** `thachdeptrai/web-kpah`, branch `main`, HEAD `710228d5a8d3f2c9e776fd9ebcade438e015fcbb` tại lúc bắt đầu audit.
- **Chuẩn hình ảnh:** concept do chủ dự án cung cấp, 9 khu: Home, Authentication, Character, Topup, Notifications, Orders, Giftcode history, Security history, Toast.
- **Phạm vi:** chỉ đọc source PHP/HTML/CSS/JS, route, tài liệu và cấu hình test. **Không đổi code chạy, SQL, API, route, form, dữ liệu, index.php root**.
- **Giới hạn xác nhận:** chưa chạy ứng dụng có DB/session, chưa có ảnh screenshot thực tế từ trình duyệt; các sai khác dưới đây được kết luận từ HTML/CSS/JS, không phải đo pixel từ website đang chạy. Chưa xác nhận XAMPP/VPS runtime trong lần audit này.
- **Định hướng:** tái tạo giao diện sky-blue/white, gradient violet-blue, CTA vàng, thẻ nổi trắng, backdrop anime, icon RPG; chỉ điều chỉnh lớp trình bày, tránh viết lại business logic.

## Kết luận chính

1. Giao diện **public** đã tiến gần concept: `app/pages/index.php` dựng hero + 4 chỉ số động + events + ranking + character gallery; `assets/css/landing.css` dùng ảnh `assets/img/home-hero-anime.webp`, card trắng và nền trời. Tuy nhiên logo trong hero là CSS text/pseudo-element (chưa bảo đảm giống wordmark concept); các nhân vật nổi bật dùng SVG minh họa, không phải sprite game; danh sách `$events` hiện là mảng cố định với liên kết chức năng, không phải feed sự kiện thực. Homepage dẫn sang các trang riêng, không chứa đầy đủ các module dài trên cùng một URL.
2. Auth **đã có lớp fantasy sáng mới**: `assets/css/auth-fantasy.css` áp cho ba form. Tuy nhiên `register.css` vẫn là base theme tối; login/recovery kế thừa lớp này và cần soát thứ tự cascade. Concept minh họa tab đăng nhập/đăng ký trong cùng một panel, còn source là các URL/form riêng. Nút social Google/Zalo trong concept chỉ là hình minh họa; không được tạo luồng OAuth giả hoặc thay logic auth.
3. Player dashboard, profile, character, inventory, topup và admin **còn khác lớn về nền/màu**: `user-home.css`, `home-theme.css`, `dashboard.css`, `player-portal.css`, `character-inventory.css`, `topup.css`, `admin.css`, `admin-home.css`, `admin-login.css` dùng background tối/gold. Việc chỉ thêm `ui-system.css` cuối file không tự biến các panel cũ thành nền trắng.
4. Component đang phân mảnh: `kp-ui-` đã có chuẩn mới, song các namespace `lp-`, `kp-auth-`, `portal-`, `pd-`, `tp-`, `uc-`, `char-ui-`, `ci-`, `gc-`, `gam-` vẫn có bảng màu, nút, khoảng cách riêng. **Có hai engine toast**: `assets/js/toast.js` xuất `kpToast()` dùng `data-toast-*`; `assets/js/ui-system.js` xuất `KpUI.toast()` dùng `data-ui-toast*`. Không được xóa toast.js khi caller còn dùng.
5. Không thay `index.php` root: `config/routes.php` map `/` sang `app/pages/index.php`, map `/tai-khoan` sang `app/pages/home.php`; các endpoint giữ nguyên. `app/router.php` và `.htaccess` nằm ngoài phạm vi UI.

## A. Map file theo nhóm

| Nhóm | PHP/HTML đang render | CSS phụ trách | JS phụ trách | Sai khác cần xử lý |
|---|---|---|---|---|
| 1. Layout public | `app/includes/public_landing.php`, `app/pages/about.php`, `downloads-public.php`, `giftcodes-public.php`, `news-public.php`, `community-public.php` | `landing.css`, `site-responsive.css`, `ui-system.css`, `giftcodes.css` (trang có dùng) | `landing.js`, `giftcodes.js`, `ui-system.js` | Header có logo chữ và menu responsive nhưng chưa có một shell giao diện hoàn toàn thống nhất với portal/admin; chuẩn hóa icon, header height, background, CTA và spacing. |
| 2. Trang chủ công khai `/` | `app/pages/index.php`; data: `app/includes/public_landing.php`, `rankings.php`, `giftcodes.php` | `landing.css`, `ui-system.css`, `site-responsive.css` | `landing.js` | Đã có bố cục tương đồng. Cần pixel-tune hero, wordmark, thông số, event card, 3 widget và mobile; artwork ranking là SVG tĩnh; event feed là placeholder link. Tránh hard-code số liệu. |
| 3. Đăng nhập / đăng ký / quên mật khẩu | `app/pages/login.php`, `register.php`, `forgot-password.php`; API `app/api/register.php`, `forgot-password.php`, `send-email-code.php`, `verify-email-code.php` chỉ làm logic | `register.css`, `login.css`, `recovery.css`, `auth-fantasy.css`, `site-responsive.css`, `ui-system.css` | `login.js`, `register.js`, `forgot-password.js`, `ui-system.js` | Đã sáng hơn; concept có tab/login split panel. Giữ route riêng, re-skin form + validation/errors/loading; không thêm Google/Zalo nếu chưa có endpoint thật. |
| 4. User dashboard `/tai-khoan`, profile `/ho-so` | `app/includes/player_portal.php`, `app/pages/home.php`, `profile.php`, `rankings.php`, `app/includes/dashboard_data.php` | `user-home.css`, `home-theme.css`, `dashboard.css`, `player-portal.css`, `user-center.css`, `site-responsive.css`, `ui-system.css` | `user-home.js` cho profile, `toast.js`, `ui-system.js` | Rail đen/icon đơn, hero và card vàng đen; concept sidebar sáng, thẻ trắng, header avatar, stats rõ. Không thay account guard / email/password submission. |
| 5. Hồ sơ nhân vật / túi đồ | `app/pages/characters.php`, `character-detail.php`, `app/includes/character_inventory.php` | `characters.css`, `character-inventory.css`, `player-portal.css`, CSS shell ở trên | `character-inventory.js` (image fallback) | Danh sách đang là card nhân vật dark/gold; chi tiết render 4 nhóm `equip`, `inven`, `bag`, `tuido` dưới dạng section/link anchor, chưa phải tab tương tác/equipment grid giống concept. Icon đọc qua `/res/icon/{id}.png`: phải bảo tồn mapping, xử lý thiếu ảnh. |
| 6a. Giftcode và thông báo | `app/pages/my-giftcodes.php`, `notifications.php`, `giftcodes-public.php`, `app/includes/user_center.php` | `user-center.css`, `giftcodes.css`, shell tương ứng | `toast.js`, `ui-system.js`, `giftcodes.js` | My giftcodes + notifications đang là danh sách `uc-record` tối, không phải bảng sáng/list icon phân trạng thái theo concept. Trang giftcode công khai tách riêng, giữ data thực. |
| 6b. Theo dõi đơn và bảo mật | `app/pages/order-tracker.php`, `security-history.php`, `app/topup/history.php` | `user-center.css`, `topup.css`, shell | `toast.js`, `ui-system.js` | Tracker dùng card + timeline; concept trình bày bảng trạng thái 5 cột. Security dùng list, concept là bảng thời gian/thiết bị/IP. Giữ mapping status và masking dữ liệu. |
| 6c. Nạp thẻ / thanh toán (được concept minh họa riêng) | `app/topup/_layout.php`, `index.php`, `card.php`, `bank.php`, `history.php`, `wallet.php`, `points.php` | `topup.css`, `site-responsive.css`, `ui-system.css` | `topup-forms.js`, `payos-create-guard.js`, `payos-embed.js`, `points-guard.js`, `vnd-exchange-guard.js`, `toast.js` | Layout `tp-shell` đen/gold, sidebar khác portal; concept trắng/xanh, tabs Viettel/Mobi/Vina, form rõ, cảnh báo nhẹ. Đơn nạp và phương thức hiện hành không được giả định giống mockup. |
| 7. Admin dashboard | `app/admin/index.php`, `login.php`, `giftcodes.php`, `downloads.php`, `referrals.php`, `key-users.php`, `game-accounts.php`, `farmer-points.php`, `farmer-history.php`, `moderation-history.php`; `app/topup/admin/{index,rates,grant,wallet-credit,orders,points}.php`; `app/includes/admin_nav.php` | `admin.css`, `admin-home.css`, `admin-nav.css`, `admin-login.css`, `admin-downloads.css`, `admin-game-accounts.css`, `admin-farmer.css`, `admin-moderation-history.css`, `giftcodes.css`, `topup.css` | `admin-nav.js`, `admin.js`, `admin-downloads.js`, `admin-game-accounts.js`, `admin-wallet-credit.js`, `ui-system.js` (theo shell) | **Concept không có mockup admin.** Đề nghị chỉ dùng cùng token/component nhưng mật độ thông tin phù hợp admin; không tự áp giả thiết pixel-perfect cho trang chưa có mẫu. Hiện có nhiều shell/dark CSS riêng. |
| 8. Bảng / form / modal / toast | `app/pages/register.php` (terms dialog), `app/admin/**`, `app/topup/**`, `app/pages/profile.php` và các list/table | `ui-system.css` (kp-ui table, form, modal, toast), `user-center.css` (kp-toast, uc-record), tất cả CSS theo trang | `ui-system.js`, `toast.js`, `register.js`, `admin-game-accounts.js`, `admin-wallet-credit.js` | Chưa thống nhất modal confirm, 2 loại toast, table stacked/scroll, field errors, buttons. Nên chuyển dần sang `kp-ui-*`; giữ hành vi submit, validation, xác nhận destructive action. |

**File khác liên quan điều hướng / dữ liệu — không sửa trong UI sprint:** `index.php` (root), `app/router.php`, `config/routes.php`, `.htaccess`, `app/includes/bootstrap.php`, `app/includes/security.php`, `app/includes/account.php`, `app/includes/topup_core.php`, `app/includes/bank_payos.php`, `app/includes/wallet_vnd.php`, `app/includes/character_inventory.php`, các `app/api/**`, callback thanh toán, SQL.

## B. CSS quyết định

**Giữ và mở rộng (core):**
- `assets/css/ui-system.css`: đã có token sky/white, violet-blue, gold CTA, radius/spacing, card, table, form, modal, tabs, toast, responsive + reduced motion. Chuẩn làm nguồn giá trị thiết kế mới.
- `assets/css/landing.css`: public home tương đồng concept (nhất là các selector `.lp-home-*` ở phần cuối). Giữ structure, chỉnh chi tiết và giảm sự trùng lặp style cũ sau khi chụp screenshot.
- `assets/css/auth-fantasy.css`: giữ foundation fantasy sáng gần đây; tinh chỉnh so với ảnh.
- `assets/css/site-responsive.css`: giữ breakpoints đã hoạt động, nhưng audit lại selector rộng như `body .shell`, `body>header` trước khi đổi shell.
- `assets/css/admin-nav.css`: giữ logic responsive/dropdown; re-skin visual có kiểm soát.

**Cần thay lớp visual đáng kể (không xóa wholesale trong một commit):**
- `assets/css/user-home.css`, `home-theme.css`, `dashboard.css`, `player-portal.css`: chuyển nền tối/gold -> sky/white/violet trong player shell; bóc selector business-neutral.
- `assets/css/characters.css`, `character-inventory.css`: thay dark panels và card/inventory layouts; giữ responsive/item image fallback logic.
- `assets/css/user-center.css`: đổi list record, alert, bảng, status sang light; không xóa toast style trước khi chuyển caller.
- `assets/css/topup.css`: chuyển topup shell + form/table/color-scheme dark; giữ riêng quy tắc layout/trạng thái thanh toán cho đến khi regression pass.
- `assets/css/admin.css`, `admin-home.css`, `admin-login.css`, `giftcodes.css`: tách nền dark cũ, đồng bộ token khi có spec admin. Các `admin-{downloads,game-accounts,farmer,moderation-history}.css` vẫn giữ cấu trúc chuyên biệt, chỉ thay da giao diện sau.

**Legacy có thể thu gọn/thay thế về sau, chưa được xóa:**
- `assets/css/register.css` đang định nghĩa `--void`, `--night`, dark `body` và nhiều selector cơ sở. Auth fantasy đang override với `.kp-auth-page`. Chỉ gỡ dark declarations khi xác nhận các form vẫn render đúng và CSS phụ thuộc như `login.css`, `recovery.css` không vỡ.
- `assets/css/login.css`, `recovery.css`: giữ các trạng thái/form đặc thù, chuyển phần skin sang auth tokens.
- `assets/css/user-auth.css`: không thấy link nạp trong các shell đã kiểm; rà toàn bộ references bằng grep trước khi đánh dấu orphan và xóa ở một task cleanup sau.

**Tài nguyên hiện có:** `assets/img/home-hero-anime.webp`, `emblem.svg`, `scene.svg`, `rank-guardian.svg`, `rank-archer.svg`, `rank-sorcerer.svg`, `favicon.svg`; game inventory image lấy từ `/res/icon/{id}.png`. Kiểm tra asset sử dụng lại để tránh dùng 1 ảnh lặp cho mọi card; các sprite game có thể không nằm trong Git tree (res hiện chủ yếu là thư mục gitignored).

## C. JS quyết định

**Giữ nguyên chức năng, chỉ đổi UI bindings có chủ đích:** 
- `register.js` (validation tên tài khoản, password, số điện thoại, email OTP, referral, điều khoản, gửi form); `login.js` (password eye/loading); `forgot-password.js` (email OTP / request).
- `user-home.js` (email verification, profile, password API); `topup-forms.js` (recalculate phí thẻ/currency); `payos-embed.js` (poll status), `payos-create-guard.js`, `points-guard.js`, `vnd-exchange-guard.js` (chống submit trùng).
- `admin-downloads.js` (XHR upload/progress), `admin-game-accounts.js` (bulk selection + exact name confirm), `admin-wallet-credit.js` (xác nhận cộng tiền), `admin-nav.js`; `admin.js` (filter rows), `giftcodes.js` (copy mã).
- `character-inventory.js`: giữ fallback ảnh `/res/icon` hỏng, có thể mở rộng tooltip/tab UI **không thay parse dữ liệu**.

**Giữ engine dùng chung, chuẩn hóa dần:**
- `ui-system.js`: ưu tiên làm toast/modal/tab/stack table controller duy nhất. Không thay event conventions khi caller chưa migrate.
- `toast.js`: **chưa xóa** vì `player_portal.php`, `topup/_layout.php` và `user-home.js` đang phụ thuộc `kpToast`/`data-toast-*`. Viết adapter chuyển dần sang `KpUI.toast`; gỡ engine cũ chỉ sau khi smoke + manual QA pass.
- `landing.js`: giữ sticky navbar, mobile menu, IntersectionObserver; chỉ tune reveal/hover.
- `scene.js`: canvas animation legacy; auth-fantasy đặt `.scene canvas{display:none}`, và các bản current auth pages được kiểm không nạp JS này. Ứng viên dọn dẹp sau khi grep toàn repo; **không xóa trong audit**.

**Không làm:** thay form `method`, `action`, `name`, CSRF, API URL, response/error contract; xóa confirm admin; thay logic status poll PayOS; tạo thanh toán mô phỏng.

## D. Thứ tự triển khai đề nghị — mỗi phase là commit riêng trên main

| Phase | Phạm vi được làm khi chủ dự án giao | Kết quả / kiểm tra đầu ra |
|---|---|---|
| 0 (audit) | Chỉ tài liệu này | Source map, CSS/JS decisions, rủi ro, checklist. Không đổi production code. |
| 1 — Design tokens & shared primitives | `ui-system.css/js` và bridge an toàn | Component button, input, card, tabs, status, toast bám ảnh; API cũ hoạt động. |
| 2 — Public homepage | `app/pages/index.php`, `public_landing.php`, `landing.css`, `landing.js`, asset nếu cần | Hero gần concept, 4 stats động, sự kiện, top ranking, character strip, gallery/CTA, responsive. |
| 3 — Authentication | `login.php`, `register.php`, `forgot-password.php`; auth CSS/JS | Form sáng đúng concept, trạng thái lỗi/OTP/loading, CSRF & API giữ nguyên. |
| 4 — Player dashboard + profile shell | `player_portal.php`, `home.php`, `profile.php`, CSS portal/dashboard/home | Sidebar trắng/xanh, header user, stats & cards, không rò thông tin. |
| 5 — Character / Inventory | `characters.php`, `character-detail.php`, CSS/JS đặc thù | Equipment grid, tabs backpack/chest, tooltip/empty state; preserve icon map, read-only. |
| 6 — Topup + 4 user-center views | `app/topup/_layout.php`, các page nạp; `my-giftcodes.php`, `notifications.php`, `order-tracker.php`, `security-history.php`; CSS tương ứng | Form/thanh toán/list/table sáng, giữ PayOS/card callback/anti-double-submit; phản hồi trạng thái chính xác. |
| 7 — Admin & component cleanup | Các trang admin và topup admin; CSS, JS confirm; toast adapter | Admin theo shared tokens (không giả pixel-perfect nếu chưa có concept riêng), bảng lớn vẫn usable; xoá CSS/JS orphan có bằng chứng. |
| 8 — QA/hardening frontend | Chỉ fix regressions UI của các phase trên | Screenshot desktop/tablet/mobile, focus/keyboard, prefers-reduced-motion, network, cache, performance. |

**Không tự khởi động phase tiếp theo khi chưa được giao.** Khi sửa thật, giới hạn commit theo một phạm vi được giao, không mass replace toàn repo.

## E. Rủi ro & biện pháp

| Rủi ro | Mức | Cách khống chế |
|---|---|---|
| CSS cascade/collision giữa `register.css`, `home-theme.css`, `topup.css` có global `:root`/`body` và light `ui-system.css` | Cao | Scoped selectors theo `body` namespace; verify computed styles trên từng route; migrate incrementally. |
| Responsive: cố định 72px rail/250px sidebar, 4-col metrics, dense tables, wallpaper cover làm nhân vật bị cắt | Cao | Screenshot ở 320/360/390/768/1024/1440/1920; kiểm tra overflow, safe areas, zoom 200%, table fallback, keyboard. |
| Cache: một số CSS/JS dùng `?v=1/2/5/6/7`, số khác dùng `filemtime`; OPcache và Cloudflare/browser lưu file cũ | Cao | Thống nhất versioning của asset được sửa; không cache HTML auth; kiểm tra response + hard reload; trên XAMPP xem PHP OPcache. |
| Form/auth: đổi DOM `id`, `name`, hidden CSRF/OTP, `action` làm hỏng JS hoặc backend | Rất cao | Giữ selector/data contract; chạy `auth_security_smoke.php`, `user_center_smoke.php` và HTTP regression. |
| Payment: overlay/button/modal ảnh hưởng nút submit, PayOS QR, polling, CSRF, callback, số tiền/phí | Rất cao | Không đổi backend; kiểm tra topup smoke + staging với dữ liệu giả, không thử giao dịch tiền thật; preserve guard scripts. |
| Admin destructive actions: thay confirm/dialog mà mất exact-name + checkbox/bulk selection | Rất cao | Giữ form/confirm semantics; chạy `tests/game_accounts_bulk_ui.test.js`, game moderation integration. |
| Inventory artwork/image fallback: `/res/icon/{id}.png` không có trên mọi môi trường | Trung bình/Cao | Giữ `kp_inventory_icon_url`; placeholder khi asset thiếu; image-rendering pixelated; chạy inventory smoke. |
| Hiển thị số liệu: home dùng account count, lastLog 5 phút, giftcode list/rankings; mockup là số ví dụ | Cao | Không hard-code mock figures, không đổi query/semantics, dùng skeleton/empty/error state. |
| Accessibility: low-contrast text rất nhỏ, toast chồng nhau, tab không keyboard, menu focus traps | Trung bình | Tap targets >=44px khi có thể, aria-live, keyboard, heading semantics, reduced motion, contrast; audit theo từng page. |
| Cập nhật `main` có thể bị VPS auto-pull ngay cả với commit tài liệu | Trung bình | Commit theo task, tránh bất kỳ đổi code ngoài phạm vi; kiểm tra CI trước khi giao phase tiếp theo. |

## F. Checklist test cho mỗi phase UI

1. `php -l` từng PHP thay đổi và `node --check` từng JS thay đổi; workflow `.github/workflows/php-js-syntax.yml`.
2. Read-only smoke phù hợp: `php tests/public_landing_smoke.php`, `php tests/responsive_smoke.php`, `php tests/auth_security_smoke.php`, `php tests/character_inventory_smoke.php`, `php tests/user_center_smoke.php`, `php tests/topup_smoke.php`, `php tests/router_smoke.php`.
3. Đối chiếu route qua `config/routes.php`; kiểm tra `GET/POST` và các URL alias; không sửa `index.php`, `.htaccess`.
4. Kiểm thử `tests/http_regression.py` bằng **database fixture `kpah_route_test`**, không dùng SQL/DB thật. Theo `docs/TESTING.md`; test Apache/.htaccess trên staging XAMPP.
5. Snapshot đối chiếu concept trên desktop 1440–1920, tablet 768–1024, mobile 320–430; assert không overflow/cover che CTA, form/readable table, trạng thái success/error/loading/empty.
6. Soát DevTools console, 404 asset, layout shifts, cache version query, truy cập bàn phím, mobile viewport và reduced motion.
7. Chỉ báo **đã test thực tế** khi đã chạy test; không coi smoke tĩnh thay cho browser validation hoặc giao dịch thật.

## Chốt phạm vi của commit audit

Chỉ thêm `docs/UI_CONCEPT_AUDIT_2026-10-17.md`. **Không đổi các file PHP, CSS, JS, index.php, .htaccess, route, SQL, assets.** Các thay đổi UI được triển khai ở task riêng do chủ dự án giao.
