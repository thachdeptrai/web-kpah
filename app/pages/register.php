<?php
/**
 * Trang đăng ký tài khoản KPAH.
 */
require __DIR__ . '/../includes/bootstrap.php';

kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

$appName    = (string) kp_config('app.name', 'KPAH');
$version    = trim((string) kp_config('app.version', ''));
$isOpen     = (bool) kp_config('registration.open', true);
$phoneMode  = 'required'; // Số điện thoại luôn bắt buộc theo yêu cầu đăng ký.
$needTerms  = (bool) kp_config('registration.terms_required', true);
$pwMin      = (int) kp_config('security.password_min', 6);
$pwMax      = (int) kp_config('security.password_max', 32);
$userHint   = (string) kp_config('security.username_hint', '');
list($patBody, $patFlags) = kp_pattern_for_js(kp_config('security.username_pattern', '/^[a-z0-9_]{4,20}$/'));

$linkLogin    = '/dang-nhap';
$linkDownload = kp_safe_url(kp_config('links.download', ''));
$linkHome     = kp_safe_url(kp_config('links.home', ''));
$linkSupport  = kp_safe_url(kp_config('links.support', ''));

$_SESSION['kp_form_ts'] = time();
$csrf = kp_csrf_token();
$canSubmit = $isOpen;
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Đăng ký tài khoản – <?= kp_e($appName) ?></title>
    <meta name="description" content="Tạo tài khoản để đăng nhập vào game <?= kp_e($appName) ?>.">
    <meta name="robots" content="noindex,follow">
    <meta name="theme-color" content="#eef7ff">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&amp;family=Cinzel:wght@700;900&amp;family=Philosopher:wght@700&amp;display=swap">
    <link rel="stylesheet" href="/assets/css/register.css?v=1">
<link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/site-responsive.css') ?>">
<link rel="stylesheet" href="/assets/css/ui-system.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/ui-system.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-fantasy.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-fantasy.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-portal-v2.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v2.css') ?>">
<link rel="stylesheet" href="/assets/css/auth-portal-v3.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/auth-portal-v3.css') ?>">
</head>
<body class="kp-auth-page kp-auth-register kp-auth-v2 kp-auth-v3">
<a class="skip-link" href="#reg-form">Bỏ qua và đến biểu mẫu đăng ký</a>
<header class="kp-auth-topbar">
  <a class="kp-auth-logo" href="/" aria-label="NGHIỆN KPAH — Trang chủ"><img src="/assets/img/kpah-logo-fantasy.svg" width="152" height="59" alt="NGHIỆN KPAH"><strong>NGHIỆN KPAH</strong></a>
  <span class="kp-auth-header-tagline">Thế giới tuổi thơ<br>Sống lại trong bạn!</span>
  <nav class="kp-auth-links" aria-label="Điều hướng website">
    <a href="/cong-dong">Cộng đồng</a>
    <a href="/gioi-thieu">Hướng dẫn</a>
    <a href="/">Trang chủ</a>
    <a class="kp-auth-download" href="/tai-game"><span aria-hidden="true">↓</span>&nbsp;<span class="kp-auth-download__text">Tải game ngay</span></a>
  </nav>
</header>

<div class="scene" aria-hidden="true">
    <canvas id="embers"></canvas>
</div>

<!-- Bộ icon -->
<svg class="sprite" width="0" height="0" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></symbol>
        <symbol id="i-phone" viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/></symbol>
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
        <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="i-eye-off" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></symbol>
        <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.5"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18h.01"/></symbol>
        <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
        <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
        <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 4v11m0 0l-4-4m4 4l4-4"/><path d="M4 20h16"/></symbol>
        <symbol id="i-login" viewBox="0 0 24 24"><path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 8l4 4-4 4M14 12H4"/></symbol>
    </defs>
</svg>

<main class="stage">
    <!-- Khu nhận diện thương hiệu -->
    <section class="brand kp-auth-story" aria-labelledby="page-title">
        <div class="kp-auth-story__intro">
        <div class="kp-auth-story__top"><span class="kp-auth-story__chapter">02 · ĐĂNG KÝ</span><span class="kp-auth-story__tag">MỞ RA CHUYẾN PHIÊU LƯU</span></div>
        <img class="kp-auth-story__logo" src="/assets/img/kpah-logo-fantasy.svg" width="760" height="292" alt="" decoding="async">
        <h1 id="page-title">Hành trình mới đang chờ bạn!</h1>
        <p class="lead">Tạo tài khoản an toàn và sẵn sàng bước vào vùng đất <?= kp_e($appName) ?>.</p>
        </div>
        <figure class="kp-auth-story__visual" aria-label="Nữ chiến binh sẵn sàng khám phá thế giới">
            <img src="/assets/img/auth-register-adventure.svg" width="760" height="710" alt="" loading="eager" decoding="async">
        </figure>

        <ul class="rules">
            <li><svg class="icon" aria-hidden="true"><use href="#i-user"/></svg><span><strong>Tên tài khoản:</strong> <?= kp_e($userHint) ?></span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg><span><strong>Mật khẩu:</strong> từ <?= $pwMin ?> đến <?= $pwMax ?> ký tự, không chứa khoảng trắng.</span></li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg><span>Không chia sẻ mật khẩu cho bất kỳ ai, kể cả người tự nhận là quản trị viên.</span></li>
        </ul>
    </section>

    <!-- Biểu mẫu -->
    <section class="panel" aria-labelledby="panel-title">
        <div class="panel-inner">
            <nav class="kp-auth-switch" aria-label="Chuyển giữa các trang tài khoản">
                <a href="/dang-nhap">Đăng nhập</a>
                <a href="/dang-ky" aria-current="page">Đăng ký</a>
                <a href="/quen-mat-khau">Quên mật khẩu</a>
            </nav>
            <div id="form-view">
                <h2 id="panel-title">Tạo tài khoản mới</h2>
                <p class="login-intro">Điền đầy đủ thông tin, xác minh email và nhập mã giới thiệu để gia nhập NGHIỆN KPAH.</p>

                <div id="alert" class="alert" role="alert" tabindex="-1" hidden>
                    <svg class="icon" aria-hidden="true"><use id="alert-icon" href="#i-alert"/></svg>
                    <div>
                        <strong id="alert-title"></strong>
                        <p id="alert-text"></p>
                    </div>
                </div>

                <?php if (!$isOpen): ?>
                    <div class="alert is-visible" data-type="warn" role="status">
                        <svg class="icon" aria-hidden="true"><use href="#i-info"/></svg>
                        <div><strong>Đăng ký đang tạm đóng</strong><p>Vui lòng quay lại sau.</p></div>
                    </div>
                <?php endif; ?>

                <noscript>
                    <div class="alert is-visible" data-type="warn" role="status">
                        <svg class="icon" aria-hidden="true"><use href="#i-info"/></svg>
                        <div><strong>Cần bật JavaScript</strong><p>Hãy bật JavaScript trên trình duyệt để gửi biểu mẫu đăng ký.</p></div>
                    </div>
                </noscript>

                <form id="reg-form" method="post" action="/api/register" novalidate autocomplete="on"
                      data-endpoint="/api/register"
                      data-user-pattern="<?= kp_e($patBody) ?>"
                      data-user-flags="<?= kp_e($patFlags) ?>"
                      data-user-hint="<?= kp_e($userHint) ?>"
                      data-pw-min="<?= $pwMin ?>"
                      data-pw-max="<?= $pwMax ?>"
                      data-phone-mode="<?= kp_e($phoneMode) ?>"
                      data-email-code-endpoint="/api/send-email-code"
                      data-email-verify-endpoint="/api/verify-email-code">
                    <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">

                    <div class="hp" aria-hidden="true">
                        <label>Để trống ô này<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <fieldset class="fields" <?= $canSubmit ? '' : 'disabled' ?>>
                        <legend class="sr-only">Thông tin đăng ký</legend>

                        <div class="field" data-field="username">
                            <label for="f-username">Tên tài khoản</label>
                            <div class="control">
                                <svg class="icon lead-icon" aria-hidden="true"><use href="#i-user"/></svg>
                                <input id="f-username" name="username" type="text" inputmode="text" autocomplete="username"
                                       autocapitalize="none" autocorrect="off" spellcheck="false" maxlength="20" required
                                       aria-describedby="h-username e-username" placeholder="vd: nguoichoi01">
                            </div>
                            <p class="hint" id="h-username"><?= kp_e($userHint) ?></p>
                            <p class="field-error" id="e-username" aria-live="polite"></p>
                        </div>

                        <?php if ($phoneMode !== 'off'): ?>
                        <div class="field" data-field="phone">
                            <label for="f-phone">Số điện thoại <span class="opt">(bắt buộc)</span></label>
                            <div class="control">
                                <svg class="icon lead-icon" aria-hidden="true"><use href="#i-phone"/></svg>
                                <input id="f-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="16"
                                       required
                                       aria-describedby="e-phone" placeholder="vd: 0912345678">
                            </div>
                            <p class="field-error" id="e-phone" aria-live="polite"></p>
                        </div>
                        <?php endif; ?>

                        <div class="field" data-field="email">
                            <label for="f-email">Email <span class="opt">(bắt buộc)</span></label>
                            <div class="control">
                                <svg class="icon lead-icon" aria-hidden="true"><use href="#i-mail"/></svg>
                                <input id="f-email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="254" required
                                       autocapitalize="none" spellcheck="false" aria-describedby="h-email e-email" placeholder="ban@example.com">
                            </div>
                            <p class="hint" id="h-email">Email dùng để xác minh và hỗ trợ khôi phục tài khoản.</p>
                            <p class="field-error" id="e-email" aria-live="polite"></p>
                            <button type="button" class="btn btn-ghost code-action" id="send-email-code">Gửi mã xác minh</button>
                        </div>

                        <div class="field" data-field="email_code">
                            <label for="f-email-code">Mã xác minh email</label>
                            <div class="control">
                                <svg class="icon lead-icon" aria-hidden="true"><use href="#i-shield"/></svg>
                                <input id="f-email-code" name="email_code" type="text" inputmode="numeric" autocomplete="one-time-code"
                                       pattern="[0-9]{6}" maxlength="6" required aria-describedby="h-email-code e-email_code" placeholder="Nhập mã 6 chữ số">
                            </div>
                            <p class="hint" id="h-email-code">Mã có hiệu lực trong 10 phút. Hãy xác minh trước khi tạo tài khoản.</p>
                            <p class="field-error" id="e-email_code" aria-live="polite"></p>
                            <button type="button" class="btn btn-ghost code-action" id="verify-email-code">Xác minh mã</button>
                            <p class="field-ok" id="email-verified" hidden><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>Email đã xác minh</p>
                        </div>

<div class="field" data-field="referral_code">
                            <label for="f-referral-code">Mã giới thiệu <span class="opt">(bắt buộc)</span></label>
                            <div class="control">
                                <svg class="icon lead-icon" aria-hidden="true"><use href="#i-shield"/></svg>
                                <input id="f-referral-code" name="referral_code" type="text" inputmode="text" autocomplete="off"
                                       maxlength="64" required autocapitalize="characters" spellcheck="false"
                                       aria-describedby="h-referral-code e-referral_code" placeholder="Nhập mã giới thiệu">
                            </div>
                            <p class="hint" id="h-referral-code">Mỗi mã có giới hạn lượt dùng và hạn sử dụng riêng.</p>
                            <p class="field-error" id="e-referral_code" aria-live="polite"></p>
                        </div>

                        <div class="field" data-field="password">
                            <label for="f-password">Mật khẩu</label>
                            <div class="control">
                                <svg class="icon lead-icon" aria-hidden="true"><use href="#i-lock"/></svg>
                                <input id="f-password" name="password" type="password" autocomplete="new-password"
                                       autocapitalize="none" spellcheck="false" maxlength="<?= $pwMax ?>" required
                                       aria-describedby="h-password e-password">
                                <button type="button" class="eye" data-toggle="f-password" aria-label="Hiện mật khẩu" aria-pressed="false">
                                    <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                                </button>
                            </div>
                            <div class="meter" id="meter" data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            <p class="hint" id="h-password"><span id="meter-label">Mật khẩu từ <?= $pwMin ?> đến <?= $pwMax ?> ký tự, không chứa khoảng trắng.</span></p>
                            <p class="field-error" id="e-password" aria-live="polite"></p>
                        </div>

                        <div class="field" data-field="password2">
                            <label for="f-password2">Nhập lại mật khẩu</label>
                            <div class="control">
                                <svg class="icon lead-icon" aria-hidden="true"><use href="#i-lock"/></svg>
                                <input id="f-password2" name="password2" type="password" autocomplete="new-password"
                                       autocapitalize="none" spellcheck="false" maxlength="<?= $pwMax ?>" required
                                       aria-describedby="e-password2">
                                <button type="button" class="eye" data-toggle="f-password2" aria-label="Hiện mật khẩu" aria-pressed="false">
                                    <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                                </button>
                            </div>
                            <p class="field-error" id="e-password2" aria-live="polite"></p>
                            <p class="field-ok" id="ok-password2" hidden><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>Mật khẩu khớp</p>
                        </div>


                        <?php if ($needTerms): ?>
                        <div class="field check" data-field="agree">
                            <label class="check-label" for="f-agree">
                                <input id="f-agree" name="agree" type="checkbox" value="1" required aria-describedby="e-agree">
                                <span class="box" aria-hidden="true"></span>
                                <span>Tôi đã đọc và đồng ý với <button type="button" class="link" id="open-terms">điều khoản sử dụng</button>.</span>
                            </label>
                            <p class="field-error" id="e-agree" aria-live="polite"></p>
                        </div>
                        <?php endif; ?>
                    </fieldset>

                    <button type="submit" id="submit" class="btn btn-primary" <?= $canSubmit ? '' : 'disabled' ?>>
                        <span class="btn-label">Tạo tài khoản</span>
                        <span class="spinner" aria-hidden="true"></span>
                    </button>

                    <?php if ($linkLogin !== ''): ?>
                        <p class="alt-link">Đã có tài khoản? <a class="btn btn-ghost" href="<?= kp_e($linkLogin) ?>">Đăng nhập</a></p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Màn hình thành công -->
            <div id="success-view" class="success" hidden>
                <div class="success-mark" aria-hidden="true"><svg class="icon"><use href="#i-check"/></svg></div>
                <h2 id="success-title" tabindex="-1">Tạo tài khoản thành công</h2>
                <p>Tài khoản <strong class="acct" id="success-user"></strong> đã sẵn sàng để đăng nhập.</p>
                <ol class="steps">
                    <li>Mở game <?= kp_e($appName) ?> trên thiết bị của bạn.</li>
                    <li>Chọn đăng nhập.</li>
                    <li>Nhập tên tài khoản và mật khẩu vừa tạo.</li>
                </ol>
                <p class="note">Hãy ghi nhớ mật khẩu. Hệ thống không lưu mật khẩu gốc nên không thể xem lại giúp bạn.</p>
                <div class="success-actions">
                    <?php if ($linkDownload !== ''): ?>
                        <a class="btn btn-primary" href="<?= kp_e($linkDownload) ?>"><svg class="icon" aria-hidden="true"><use href="#i-download"/></svg>Tải game</a>
                    <?php endif; ?>
                    <?php if ($linkLogin !== ''): ?>
                        <a class="btn btn-ghost" href="<?= kp_e($linkLogin) ?>"><svg class="icon" aria-hidden="true"><use href="#i-login"/></svg>Đăng nhập</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-ghost" id="again">Đăng ký tài khoản khác</button>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <span>&copy; <?= date('Y') ?> <?= kp_e($appName) ?>. Bảo lưu mọi quyền.</span>
    <?php if ($version !== ''): ?><span>Phiên bản <?= kp_e($version) ?></span><?php endif; ?>
    <?php if ($linkHome !== ''): ?><a href="<?= kp_e($linkHome) ?>">Trang chủ</a><?php endif; ?>
    <?php if ($linkSupport !== ''): ?><a href="<?= kp_e($linkSupport) ?>">Hỗ trợ</a><?php endif; ?>
</footer>

<?php if ($needTerms): ?>
<dialog id="terms" aria-labelledby="terms-title">
    <div class="dlg-head">
        <h2 id="terms-title">Điều khoản sử dụng</h2>
        <button type="button" class="icon-btn" id="terms-close" aria-label="Đóng"><svg class="icon" aria-hidden="true"><use href="#i-x"/></svg></button>
    </div>
    <div class="dlg-body">
        <?php require __DIR__ . '/../includes/terms_content.php'; ?>
    </div>
    <div class="dlg-foot">
        <button type="button" class="btn btn-primary" id="terms-accept">Tôi đồng ý</button>
    </div>
</dialog>
<?php endif; ?>

<script src="/assets/js/register.js?v=5" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
</body>
</html>
