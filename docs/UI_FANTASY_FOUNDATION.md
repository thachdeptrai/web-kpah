# Fantasy UI Foundation v1 — NGHIỆN KPAH

## Mục tiêu

Cung cấp nền tảng game UI fantasy/anime sáng có thể tái sử dụng ở các bước migrate tiếp theo, không thay route hoặc backend. Triển khai trên `main`. Concept chủ dự án gửi ngày 17/10/2026 là nguồn visual chính. HTML/CSS/JS vẫn framework-free.

**Assets mới**

- `assets/img/fantasy-world.svg`: background vector nhiều lớp (trời, mây, đảo nổi, đình, thác, foliage, petals), không nhúng chữ/nút hay mockup tĩnh.
- `assets/img/kpah-logo-main.webp`: logo minh họa fantasy raster, nền trong suốt, dùng thống nhất ở hero, header và footer.
- `assets/css/fantasy-foundation.css`: namespace `kpf-`, token `--kpf-*`.
- `assets/js/fantasy-foundation.js`: namespace `window.KpFantasy`, không trùng sự kiện `data-ui-*` cũ.

**Nạp vào web**

`ui-system.css` import `fantasy-foundation.css`. Public/auth/player/topup/admin nav shell tải `fantasy-foundation.js` theo `filemtime`. Admin dùng `admin-nav.css` đã import `ui-system.css?v=2`. Các trang chưa migrate giao diện tiếp tục dùng selector cũ, không bị CSS global của `kpf-` tự ý thay form.

## Design tokens

| Nhóm | Token chính |
|---|---|
| Background | `--kpf-gradient-sky`, `--kpf-sky-50/100/200`, `--kpf-paper-glass` |
| Accent | `--kpf-violet`, `--kpf-blue`, `--kpf-gold` |
| State | `--kpf-mint`, `--kpf-amber`, `--kpf-rose` |
| Ink/lines | `--kpf-ink`, `--kpf-text`, `--kpf-muted`, `--kpf-line` |
| Typography | `--kpf-font-ui`, `--kpf-font-display`, `--kpf-text-*`, `--kpf-heading-*` |
| Space | `--kpf-space-1/2/3/4/5/6/8/10/12/16` |
| Radius | `--kpf-radius-xs/sm/md/lg/xl/pill` |
| Shadow/motion | `--kpf-shadow-sm`, `--kpf-shadow`, `--kpf-shadow-float`, `--kpf-fast/normal/slow`, `--kpf-ease` |

Typography dùng Be Vietnam Pro cho text và Baloo 2 (kèm fallback) cho title lớn, ưu tiên cỡ 14–16px trên các component mới. Không dùng font tùy chỉnh đẩy file nặng vào repo. Logo chữ dùng styling vector gradient/outline riêng.

## Reusable components

```html
<section class="kpf-hero kpf-scope">
  <div class="kpf-hero__content">
    <span class="kpf-eyebrow">THẾ GIỚI MỚI</span>
    <h1 class="kpf-heading kpf-heading--hero">Hành trình tuổi thơ</h1>
    <p class="kpf-hero__subtitle">Một cuộc phiêu lưu đang chờ.</p>
    <div class="kpf-hero__actions">
      <a class="kpf-btn kpf-btn--primary" href="/tai-game">Tải game</a>
      <a class="kpf-btn kpf-btn--gold" href="/giftcode">Nhận quà</a>
    </div>
  </div>
  <div class="kpf-hero__art"><img src="/assets/img/kpah-logo-main.webp" alt="NGHIỆN KPAH"></div>
</section>

<div class="kpf-card-grid kpf-scope">
  <article class="kpf-card kpf-card--interactive"><h2 class="kpf-card__title">Trang bị</h2><p class="kpf-card__copy">Thông tin</p></article>
  <article class="kpf-card"><h2 class="kpf-card__title">Hành trang</h2></article>
</div>

<section class="kpf-admin-panel kpf-scope">
  <header class="kpf-admin-panel__toolbar"><h2 class="kpf-heading kpf-heading--small">Quản trị</h2></header>
  <div class="kpf-table-wrap"><table class="kpf-table"><thead><tr><th>Đơn</th><th>Trạng thái</th></tr></thead>
    <tbody><tr><td>#12345</td><td><span class="kpf-badge kpf-badge--success">Hoàn tất</span></td></tr></tbody></table></div>
</section>
```

**Component available:** `kpf-world` (scene layers), `kpf-glass`, `kpf-panel`, `kpf-card`, `kpf-hero`, `kpf-btn--primary/gold/soft/outline/danger`, `kpf-input/select/textarea/field`, `kpf-stats/stat`, `kpf-badge--success/pending/warning/error/info`, `kpf-table(-wrap/--stack)`, `kpf-tabs/tab/tab-panel`, `kpf-portal/sidebar/nav-link`, `kpf-admin-panel`, `kpf-modal`, `kpf-toast`.

## JS and accessibility

```js
KpFantasy.toast({type: 'success', title: 'Thành công', message: 'Đã lưu thông tin.'});
KpFantasy.setBusy(button, true, 'Đang xử lý…');
KpFantasy.setBusy(button, false);
```

- Toast delegates to `KpUI.toast` if present. `kpToast` cũ vẫn nguyên, chưa xóa caller.
- Tab opt-in: `[data-kpf-tabs]` chứa `[role=tab][aria-controls=<panel-id>]`, keyboard Left/Right/Home/End, panel hidden, event `kpf:tabchange`.
- Modal: `<dialog class="kpf-modal" id="...">` + `data-kpf-modal-open="#..."` + `data-kpf-modal-close`.
- Mobile portal nav: `data-kpf-nav-toggle` + `aria-controls` trỏ `id` của `data-kpf-nav`; click/Escape/resize có trạng thái đồng bộ.
- Table stacked: `.kpf-table--stack` tự copy column headers vào `td[data-label]`; CSS responsive dưới 760px.
- Reveal: `data-kpf-reveal` dùng IntersectionObserver; giảm hiệu ứng khi user chọn `prefers-reduced-motion`.
- Toast `role=alert` cho error, `role=status` cho các tình huống khác; trạng thái giữ nguyên văn bản thật.

## Home changes theo yêu cầu

`app/pages/index.php` thay đúng ba khối hiện trên web là **Top Sức mạnh**, **Nhân vật nổi bật**, **Khám phá KPAH** bằng:

1. **Khởi đầu hành trình** — 3 bước newbie + liên kết `/dang-ky`.
2. **Quà chờ chủ nhân** — count giftcode live, liên kết `/giftcode`.
3. **Đi cùng đồng đội** — cộng đồng, tên nhân vật trong ranking khi có dữ liệu, giữ route `/bang-xep-hang`.

Banner chính lấy logo vector mới; nền sky SVG với phần anime nhân vật WebP cũ blend nhẹ; chữ thống kê/sự kiện và navigation tăng cỡ. **Không hard-code thống kê 1.285 / 52.316 / 8.731 / 127 của ảnh mẫu**. Dữ liệu lấy từ PHP như trước.

## Migration discipline

1. Chỉ migrate visual một trang khi được giao: thêm `kpf-scope`, thay class trực quan; không thay input `name`, form `action`, hidden `csrf`, API endpoint, ID dùng bởi JS.
2. Tránh các class chung `.card`, `.panel` khi dùng foundation; namespace `kpf-` chống collision.
3. Không chuyển payment/table/admin confirmation sang animation/JS mới khi chưa có smoke test.
4. Giữ lazy-loading artwork, tránh đưa một mockup PNG nguyên trang thành HTML background.
5. Responsive check 320 / 375 / 768 / 1024 / 1440 / 1920; font zoom 200%, focus keyboard, Reduced Motion, screenshot; kiểm tra tràn chữ/tables và loading network.

## Testing

- Static guard: `php tests/fantasy_foundation_smoke.php`.
- Existing: `php tests/public_landing_smoke.php`, `php tests/responsive_smoke.php`, `php tests/auth_security_smoke.php`, `php tests/character_inventory_smoke.php`, `php tests/router_smoke.php`, `php tests/topup_smoke.php`.
- PHP lint `php -l` / JS lint `node --check`; CI `.github/workflows/php-js-syntax.yml` and `routing-regression.yml`.
- Static/CI tests do not constitute visual screenshot proof. Current concept alignment must be verified in XAMPP staging/browser before claiming pixel accuracy.
- `index.php` root, routes, SQL, backend, auth endpoints and payment callbacks are out of scope and unchanged.
