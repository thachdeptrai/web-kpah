# NGHIỆN KPAH UI Foundation

The shared UI foundation lives in:

- `assets/css/ui-system.css`
- `assets/js/ui-system.js`

It is intentionally framework-free and uses the `kp-ui-` prefix so legacy pages can migrate without breaking their PHP, forms, routes, or JavaScript.

## Rules

1. Use the design tokens from `:root`; do not introduce page-specific copies of colors, radius, shadow, or spacing.
2. New reusable components must use the `kp-ui-` prefix.
3. Wrap legacy markup in `.kp-ui-scope` only when its generic form/table bridge has been checked visually.
4. Keep API URLs, input names, CSRF fields, form methods, and route paths unchanged during UI migrations.
5. Load `ui-system.css` after page styles while migrating a page. Remove legacy styles only after the page passes visual and functional regression checks.
6. Respect `prefers-reduced-motion`; animations must use transform/opacity where practical.

## Core classes

### Card and panel

```html
<article class="kp-ui-card kp-ui-card--interactive">
  <span class="kp-ui-eyebrow">Sự kiện</span>
  <h2 class="kp-ui-heading">Đua Top Lực Chiến</h2>
  <p class="kp-ui-copy">Nội dung ngắn của thẻ.</p>
</article>

<section class="kp-ui-panel">
  <header class="kp-ui-panel-head"><h2 class="kp-ui-heading">Tiêu đề</h2></header>
  <div class="kp-ui-panel-body">Nội dung</div>
</section>
```

### Buttons and fields

```html
<button class="kp-ui-button" type="submit">Xác nhận</button>
<a class="kp-ui-button kp-ui-button--secondary" href="/">Quay lại</a>

<label class="kp-ui-field">
  <span class="kp-ui-label">Tài khoản</span>
  <input class="kp-ui-input" name="username" autocomplete="username">
  <small class="kp-ui-help">Dùng tên tài khoản trong game.</small>
</label>
```

### Status

Use one of `success`, `warning`, `danger`, or `info`.

```html
<span class="kp-ui-badge kp-ui-badge--success">Hoàn thành</span>
```

### Responsive table

```html
<div class="kp-ui-table-wrap">
  <table class="kp-ui-table kp-ui-table--stack">
    <thead><tr><th>Mã đơn</th><th>Trạng thái</th></tr></thead>
    <tbody><tr><td>#12345</td><td>Hoàn thành</td></tr></tbody>
  </table>
</div>
```

The JavaScript automatically copies header text into `data-label` for stacked mobile rows.

### Tabs

```html
<div class="kp-ui-tabs" data-ui-tabs role="tablist">
  <button class="kp-ui-tab" role="tab" aria-controls="equipment" aria-selected="true">Trang bị</button>
  <button class="kp-ui-tab" role="tab" aria-controls="inventory">Hành trang</button>
</div>
<section id="equipment" data-ui-tab-panel>...</section>
<section id="inventory" data-ui-tab-panel hidden>...</section>
```

### Modal

```html
<button class="kp-ui-button" data-ui-modal-open="#confirm-modal">Mở</button>
<dialog class="kp-ui-modal" id="confirm-modal" data-ui-close-backdrop>
  <header class="kp-ui-modal-head">
    <h2 class="kp-ui-heading">Xác nhận</h2>
    <button data-ui-modal-close type="button">×</button>
  </header>
  <div class="kp-ui-modal-body">Nội dung</div>
  <footer class="kp-ui-modal-foot">
    <button class="kp-ui-button kp-ui-button--ghost" data-ui-modal-close type="button">Hủy</button>
  </footer>
</dialog>
```

### Toast

Prefer the JavaScript API for client-side events:

```js
KpUI.toast({
  type: 'success',
  title: 'Thành công',
  message: 'Thao tác đã hoàn tất.'
});
```

Server-rendered pages can emit a declarative toast:

```html
<div hidden
     data-ui-toast="success"
     data-ui-toast-title="Thành công"
     data-ui-toast-message="Thao tác đã hoàn tất."></div>
```

### Background and stats

Add `data-ui-backdrop` to `body` only when the page is ready to use the shared bright background.

```html
<body data-ui-backdrop>
  <section class="kp-ui-stats">
    <article class="kp-ui-stat">
      <span class="kp-ui-stat-icon">★</span>
      <div><strong>1.285</strong><span>Đang online</span></div>
    </article>
  </section>
</body>
```

## Exposed JavaScript API

- `KpUI.toast(options)`
- `KpUI.openModal(selectorOrElement)`
- `KpUI.closeModal(selectorOrElement)`
- `KpUI.activateTab(tabElement, focus)`

The foundation dispatches `kpui:ready` on `document` and `kpui:tabchange` on the tab list.
