<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/player_portal.php';
$user=kp_player_account();
$csrf=kp_csrf_token();
$username=(string)$user['username'];
$email=trim((string)($user['email']??''));
$phone=trim((string)($user['phone']??''));
$hasEmail=$email!=='' && filter_var($email,FILTER_VALIDATE_EMAIL);
$initial=function_exists('mb_substr')?mb_strtoupper(mb_substr($username,0,1,'UTF-8'),'UTF-8'):strtoupper(substr($username,0,1));
$appName=(string)kp_config('app.name','KPAH');
$registeredAt='';
if(!empty($user['regdate'])){
    $date=is_numeric($user['regdate'])?(int)$user['regdate']:strtotime((string)$user['regdate']);
    if($date!==false && $date>0)$registeredAt=date('d/m/Y',$date);
}
kp_player_head('Hồ sơ & bảo mật','profile',$user);
?>
<section class="portal-hero portal-hero--compact">
    <div class="portal-hero-copy"><span class="portal-kicker"><i></i> TÀI KHOẢN CỦA BẠN</span>
    <h1>Hồ sơ <em>& bảo mật.</em></h1><p>Cập nhật thông tin liên hệ và đổi mật khẩu ở trang riêng. Dữ liệu chỉ hiển thị cho tài khoản đang đăng nhập.</p></div>
    <a class="portal-hero-action" href="characters.php">Nhân vật của tôi ↗</a>
</section>
<section class="account-section" id="profile" aria-labelledby="profile-title">
        <div class="section-heading">
            <div>
                <p class="kicker">THÔNG TIN TÀI KHOẢN</p>
                <h2 id="profile-title">Thông tin của bạn</h2>
            </div>
            <p>Cập nhật thông tin liên hệ hoặc đổi mật khẩu của bạn.</p>
        </div>

        <div class="profile-layout">
            <section class="profile-card" aria-labelledby="contact-title">
                <div class="card-heading">
                    <div>
                        <span class="card-index">HỒ SƠ</span>
                        <h3 id="contact-title">Thông tin tài khoản</h3>
                    </div>
                    <span class="edit-symbol" aria-hidden="true">↗</span>
                </div>

                <div class="profile-identity">
                    <div class="big-avatar" aria-hidden="true"><?= kp_e($initial) ?></div>
                    <div class="identity-copy">
                        <strong><?= kp_e($username) ?></strong>
                        <span><?= kp_e($appName) ?><?= $registeredAt !== '' ? ' · Tham gia ' . kp_e($registeredAt) : '' ?></span>
                    </div>
                    <span class="identity-tag">TÀI KHOẢN</span>
                </div>

                <form id="profile-form" data-endpoint="api/user-profile.php">
                    <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
                    <div class="profile-field">
                        <label for="email">Email <?= $hasEmail ? 'muốn thay đổi' : 'liên kết' ?></label>
                        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" <?= $hasEmail ? 'required' : 'readonly aria-describedby="email-help"' ?> value="<?= kp_e($email) ?>" placeholder="<?= $hasEmail ? 'name@example.com' : 'Chưa có email' ?>">
                        <p class="field-note" id="email-help"><?= $hasEmail ? 'Khi đổi email, cần xác minh địa chỉ hiện tại và địa chỉ mới.' : 'Tài khoản chưa có email. Để liên kết an toàn, hãy liên hệ hỗ trợ bên dưới.' ?></p>
                        <?php if (!$hasEmail): ?><a class="profile-help-link" href="https://zalo.me/g/zmyedl5en7sldmd9el1z" target="_blank" rel="noopener noreferrer">Liên hệ hỗ trợ ↗</a><?php endif; ?>
                    </div>
                    <?php if ($hasEmail): ?>
                    <div class="profile-field">
                        <label for="old_email_code">Mã xác minh Gmail cũ</label>
                        <div class="input-with-action">
                            <input id="old_email_code" name="old_email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Mã 6 chữ số từ Gmail cũ">
                            <button type="button" id="send-old-code">Gửi mã</button>
                        </div>
                    </div>
                    <div class="profile-field">
                        <label for="email_code">Mã xác minh Gmail mới</label>
                        <div class="input-with-action">
                            <input id="email_code" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Mã 6 chữ số từ Gmail mới">
                            <button type="button" id="send-code">Xác minh và gửi mã mới</button>
                        </div>
                        <p class="field-note">Mã gửi đến email có thời hạn. Chỉ nhập mã trên trang này.</p>
                    </div>
                    <?php else: ?>
                    <input type="hidden" id="old_email_code" name="old_email_code" value="">
                    <input type="hidden" id="email_code" name="email_code" value="">
                    <button type="button" id="send-old-code" hidden>Gửi mã</button>
                    <button type="button" id="send-code" hidden>Gửi mã</button>
                    <?php endif; ?>
                    <div class="profile-field">
                        <label for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="tel" maxlength="16" autocomplete="tel" placeholder="0xxxxxxxxx hoặc +84xxxxxxxxx" value="<?= kp_e($phone) ?>">
                    </div>
                    <div class="form-feedback" id="profile-feedback" role="status" aria-live="polite"></div>
                    <div class="profile-form-footer">
                        <span class="form-lock"><span aria-hidden="true">◇</span> <?= $hasEmail ? 'Đổi email phải xác minh hai địa chỉ' : 'Vẫn có thể cập nhật số điện thoại' ?></span>
                        <button type="submit" class="save-button"><span>Lưu thay đổi</span><span aria-hidden="true">↗</span></button>
                    </div>
                </form>
            </section>

            <aside class="security-card" id="security" aria-labelledby="security-title">
                <div class="security-card-top"><span class="security-overline">BẢO MẬT</span><span class="security-status"><i></i> ĐANG DÙNG</span></div>
                <div class="security-art" aria-hidden="true">
                    <div class="security-orbit orbit-a"></div>
                    <div class="security-orbit orbit-b"></div>
                    <div class="shield-shape"><span>✓</span></div>
                    <span class="security-spark spark-a">✦</span>
                    <span class="security-spark spark-b">✧</span>
                </div>
                <p class="kicker">BẢO MẬT TÀI KHOẢN</p>
                <h3 id="security-title">Giữ tài khoản của bạn an toàn.</h3>
                <p class="security-description">Không dùng chung mật khẩu với tài khoản khác. Đừng chia sẻ mã xác minh.</p>

                <div class="security-row">
                    <span><i></i> Phiên đăng nhập</span>
                    <b>Đang hoạt động</b>
                </div>
                <div class="security-row">
                    <span><i class="<?= $hasEmail ? 'verified' : 'pending' ?>"></i> Email liên kết</span>
                    <b><?= $hasEmail ? 'Đã liên kết' : 'Chưa liên kết' ?></b>
                </div>

                <div class="password-change">
                    <div class="password-change-heading">
                        <div><span class="card-index">MẬT KHẨU</span><h4>Đổi mật khẩu</h4></div>
                        <span class="password-key" aria-hidden="true">⌁</span>
                    </div>
                    <p class="password-help">Nhập mật khẩu hiện tại, sau đó đặt mật khẩu mới.</p>
                    <form id="change-password-form" data-endpoint="api/change-password.php" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
                        <div class="profile-field">
                            <label for="current_password">Mật khẩu hiện tại</label>
                            <input id="current_password" name="current_password" type="password" minlength="6" maxlength="128" autocomplete="current-password" placeholder="Nhập mật khẩu hiện tại" required>
                        </div>
                        <div class="profile-field">
                            <label for="new_password">Mật khẩu mới</label>
                            <input id="new_password" name="new_password" type="password" minlength="6" maxlength="32" autocomplete="new-password" placeholder="Từ 6 đến 32 ký tự" required>
                        </div>
                        <div class="profile-field">
                            <label for="confirm_password">Xác nhận mật khẩu mới</label>
                            <input id="confirm_password" name="confirm_password" type="password" minlength="6" maxlength="32" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới" required>
                        </div>
                        <div class="form-feedback" id="password-feedback" role="status" aria-live="polite"></div>
                        <button type="submit" id="change-password-submit" class="save-button password-submit"><span>Cập nhật mật khẩu</span><span aria-hidden="true">↗</span></button>
                    </form>
                </div>
                <a class="security-link" href="/quen-mat-khau"><span>Quên mật khẩu?</span><span aria-hidden="true">→</span></a>
                <p class="security-footnote"><span aria-hidden="true">✦</span> Đừng chia sẻ mật khẩu hay mã xác minh cho người khác.</p>
            </aside>
        </div>
    </section>

    
<div class="portal-return"><a href="home.php">← Tổng quan</a><a href="rankings.php">Xem xếp hạng ↗</a></div>
<?php kp_player_foot(true); ?>
