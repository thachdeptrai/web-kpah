<?php
require __DIR__ . '/includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['kp_user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['kp_user_last_seen']) && time() - (int) $_SESSION['kp_user_last_seen'] > 1800) {
    unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
    header('Location: login.php?expired=1');
    exit;
}
$_SESSION['kp_user_last_seen'] = time();

try {
    $st = kp_db()->prepare('SELECT id, userid, username, email, phone, regdate FROM team_user WHERE id = ? LIMIT 1');
    $st->execute([(int) $_SESSION['kp_user_id']]);
    $user = $st->fetch();

    if (!$user) {
        unset($_SESSION['kp_user_id'], $_SESSION['kp_user_username'], $_SESSION['kp_user_last_seen']);
        header('Location: login.php');
        exit;
    }
} catch (Throwable $e) {
    kp_log('tải trang tài khoản thất bại', $e);
    http_response_code(503);
    exit('Chưa thể tải trang tài khoản. Vui lòng thử lại sau.');
}

// Liên kết nhân vật đối chiếu từ bản dump: tob_web_info_char.userid khớp team_user.id.
// Không dùng team_user.userid để truy vấn nhân vật; đó là một mã khác.
// Chỉ lấy nhân vật của phiên đăng nhập, các thông tin chi tiết là riêng tư.
$characters = [];
$charactersUnavailable = false;
try {
    $charStmt = kp_db()->prepare(
        'SELECT w.charname,
                COALESCE(l.lv, c.lastLv, w.lv) AS lastLv,
                COALESCE(l.exp, c.xp, 0) AS xp,
                COALESCE(c.gold, l.gold, 0) AS gold,
                COALESCE(c.luong, l.luong, 0) AS luong,
                COALESCE(c.luonglock, 0) AS luonglock,
                c.lastLog, clan.clanName
         FROM tob_web_info_char AS w
         LEFT JOIN tob_char AS c ON c.charname = w.charname AND c.userId = w.userid
         LEFT JOIN tob_char_luong AS l ON l.charname = w.charname
         LEFT JOIN tob_clan AS clan ON clan.id_clan = COALESCE(c.idClan, l.idclan) AND clan.isdel = 0
         WHERE w.userid = ?
         ORDER BY COALESCE(c.lastLv, l.lv, w.lv) DESC, COALESCE(c.xp, l.exp, 0) DESC
         LIMIT 8'
    );
    $charStmt->execute([(int) $user['id']]);
    $characters = $charStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $charactersUnavailable = true;
    kp_log('không tải được nhân vật của tài khoản', $e);
}

// Top cấp độ công khai theo bảng nhân vật KPAH (không truy vấn bảng player của NRO).
$topPlayers = [];
$rankingUnavailable = false;
try {
    $rankStmt = kp_db()->query(
        'SELECT charname, lv, exp
         FROM tob_char_luong
         ORDER BY lv DESC, exp DESC, id ASC
         LIMIT 10'
    );
    $topPlayers = $rankStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rankingUnavailable = true;
    kp_log('không tải được top cấp độ', $e);
}

$csrf = kp_csrf_token();
$username = (string) ($user['username'] ?? 'Player');
$email = trim((string) ($user['email'] ?? ''));
$phone = trim((string) ($user['phone'] ?? ''));
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($username, 0, 1));
$appName = (string) kp_config('app.name', 'KPAH');
$playerId = (string) $user['id'];
$registeredAt = '';
if (!empty($user['regdate'])) {
    $registeredTimestamp = is_numeric($user['regdate']) ? (int) $user['regdate'] : strtotime((string) $user['regdate']);
    if ($registeredTimestamp !== false && $registeredTimestamp > 0) {
        $registeredAt = date('d/m/Y', $registeredTimestamp);
    }
}
$hasEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
$numberFormat = function ($number) {
    return number_format((float) $number, 0, ',', '.');
};
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0a16">
    <meta name="description" content="Quản lý hồ sơ, thông tin liên hệ và bảo mật tài khoản <?= kp_e($appName) ?>.">
    <meta name="robots" content="noindex, nofollow">
    <title>Tài khoản của tôi · <?= kp_e($appName) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&amp;family=Cinzel:wght@700;900&amp;family=Philosopher:wght@700&amp;display=swap">
    <link rel="stylesheet" href="assets/css/register.css?v=1">
    <link rel="stylesheet" href="assets/css/user-home.css?v=6">
    <link rel="stylesheet" href="assets/css/home-theme.css?v=7">
    <link rel="stylesheet" href="assets/css/characters.css?v=2">
</head>
<body class="home-page">

<a class="home-skip-link" href="#overview">Đi đến nội dung chính</a>

<aside class="side-rail" aria-label="Điều hướng tài khoản">
    <a class="rail-logo" href="/tai-khoan" aria-label="<?= kp_e($appName) ?>">K<span>.</span></a>
    <div class="rail-line" aria-hidden="true"></div>
    <a class="rail-icon active" href="#overview" aria-label="Tổng quan" title="Tổng quan"><span aria-hidden="true">⌂</span></a>
    <a class="rail-icon" href="#profile" aria-label="Hồ sơ tài khoản" title="Hồ sơ"><span aria-hidden="true">◎</span></a>
    <a class="rail-icon" href="#characters" aria-label="Nhân vật" title="Nhân vật"><span aria-hidden="true">♙</span></a>
    <a class="rail-icon" href="nap.php" aria-label="Nạp tài nguyên" title="Nạp tài nguyên"><span aria-hidden="true">✦</span></a>
    <a class="rail-icon" href="#community" aria-label="Cộng đồng" title="Cộng đồng"><span aria-hidden="true">♧</span></a>
    <a class="rail-icon" href="#security" aria-label="Bảo mật" title="Bảo mật"><span aria-hidden="true">◇</span></a>
    <div class="rail-bottom">
        <a class="rail-icon rail-logout" href="api/logout.php" aria-label="Đăng xuất" title="Đăng xuất"><span aria-hidden="true">↗</span></a>
    </div>
</aside>

<main class="home-main">
    <header class="home-top">
        <div class="home-breadcrumb"><span class="breadcrumb-kicker">KPAH</span><span class="breadcrumb-separator">/</span><span>TÀI KHOẢN CỦA TÔI</span></div>
        <div class="top-user">
            <span class="top-status"><i></i> ĐANG ĐĂNG NHẬP</span>
            <span class="top-user-name"><?= kp_e($username) ?></span>
            <span class="mini-avatar" aria-hidden="true"><?= kp_e($initial) ?></span>
            <a class="top-logout" href="api/logout.php">Đăng xuất <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <section class="welcome-block" id="overview" aria-labelledby="welcome-title">
        <div class="welcome-copy">
            <p class="kicker"><span class="kicker-mark"></span> KHU VỰC NGƯỜI CHƠI</p>
            <h1 id="welcome-title">Xin chào, <em><?= kp_e($username) ?>!</em></h1>
            <p class="welcome-description">Quản lý thông tin tài khoản, cập nhật Gmail và đổi mật khẩu ngay tại đây.</p>
            <div class="welcome-actions">
                <a class="jump-link" href="#profile">Quản lý hồ sơ <span aria-hidden="true">↘</span></a>
                <a class="jump-link" href="topup/index.php">Nạp tài nguyên <span aria-hidden="true">↗</span></a>
                <a class="jump-link" href="nap.php">Nạp tài nguyên <span aria-hidden="true">↗</span></a>
                <span class="welcome-secure">Chúc bạn chơi game vui vẻ.</span>
            </div>
        </div>

        
    </section>

    <section class="metric-strip" aria-label="Tóm tắt tài khoản">
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">⌘</span>
            <span class="metric-label">MÃ TÀI KHOẢN</span>
            <strong>#<?= kp_e($playerId) ?></strong>
            <small>Mã tài khoản KPAH</small>
        </article>
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">✉</span>
            <span class="metric-label">EMAIL LIÊN KẾT</span>
            <strong class="metric-value"><?= $email !== '' ? kp_e($email) : 'Chưa liên kết' ?></strong>
            <small><?= $hasEmail ? 'Dùng để nhận mã xác minh' : 'Chưa có email; liên hệ hỗ trợ để liên kết an toàn' ?></small>
        </article>
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true">⌕</span>
            <span class="metric-label">SỐ ĐIỆN THOẠI</span>
            <strong class="metric-value"><?= $phone !== '' ? kp_e($phone) : 'Chưa cập nhật' ?></strong>
            <small>Số liên hệ của tài khoản</small>
        </article>
        <article class="metric-card metric-card-status">
            <span class="metric-icon" aria-hidden="true">✧</span>
            <span class="metric-label">TRẠNG THÁI</span>
            <strong class="state-value"><i></i> ĐANG ĐĂNG NHẬP</strong>
            <small><?= $registeredAt !== '' ? 'Tham gia từ ' . kp_e($registeredAt) : 'Tài khoản KPAH' ?></small>
        </article>
    </section>

    <section class="characters-section" id="characters" aria-labelledby="characters-title">
        <div class="characters-intro">
            <div class="characters-intro-copy">
                <p class="characters-eyebrow"><span class="characters-eyebrow-glyph" aria-hidden="true">✦</span> HỒ SƠ NHÂN VẬT <span class="characters-eyebrow-line" aria-hidden="true"></span> KPAH</p>
                <h2 id="characters-title">Nhân vật <em>của bạn</em></h2>
                <p class="characters-intro-note">Những nhân vật đang gắn với tài khoản này.</p>
            </div>
            <?php if (!$charactersUnavailable && !empty($characters)): ?>
                <div class="characters-count" aria-label="Đang hiển thị <?= count($characters) ?> nhân vật">
                    <span class="characters-count-number"><?= str_pad((string) count($characters), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="characters-count-copy">NHÂN VẬT<br>ĐANG HIỂN THỊ</span>
                    <span class="characters-count-ornament" aria-hidden="true">✧</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($charactersUnavailable): ?>
            <div class="char-ui-empty" role="status">
                <div class="char-ui-empty-seal" aria-hidden="true">✧</div>
                <div><h3>Chưa tải được nhân vật</h3><p>Thử tải lại trang sau ít phút nhé.</p></div>
            </div>
        <?php elseif (empty($characters)): ?>
            <div class="char-ui-empty">
                <div class="char-ui-empty-seal" aria-hidden="true">✧</div>
                <div><h3>Chưa có nhân vật nào</h3><p>Vào game tạo nhân vật, thông tin sẽ xuất hiện tại đây.</p></div>
            </div>
        <?php else: ?>
            <div class="characters-grid">
                <?php foreach ($characters as $index => $character): ?>
                    <?php
                    $charName = (string) ($character['charname'] ?? '');
                    $clanName = trim((string) ($character['clanName'] ?? ''));
                    $charInitial = function_exists('mb_substr')
                        ? mb_strtoupper(mb_substr($charName, 0, 1, 'UTF-8'), 'UTF-8')
                        : strtoupper(substr($charName, 0, 1));
                    $lastSeen = '';
                    if (!empty($character['lastLog']) && (string) $character['lastLog'] !== '2010-01-01 00:00:00') {
                        $stamp = strtotime((string) $character['lastLog']);
                        if ($stamp !== false) {
                            $lastSeen = date('d/m/Y H:i', $stamp);
                        }
                    }
                    ?>
                    <article class="char-ui-card<?= $index === 0 ? ' char-ui-card--featured' : '' ?>">
                        <div class="char-ui-corner char-ui-corner--tl" aria-hidden="true"></div>
                        <div class="char-ui-corner char-ui-corner--br" aria-hidden="true"></div>
                        <div class="char-ui-card-top">
                            <span class="char-ui-index"><span aria-hidden="true">✦</span> NHÂN VẬT <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="char-ui-top-mark" aria-hidden="true">KPAH <i></i> HỒ SƠ</span>
                        </div>
                        <div class="char-ui-card-layout">
                            <div class="char-ui-portrait" aria-hidden="true">
                                <div class="char-ui-portrait-glow"></div>
                                <svg class="char-ui-crest" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M100 12 164 46 179 115 143 170 100 191 57 170 21 115 36 46 100 12Z" stroke="currentColor" stroke-width="1.4"/>
                                    <path d="m100 30 48 28 14 57-28 42-34 17-34-17-28-42 14-57 48-28Z" stroke="currentColor" stroke-width=".8"/>
                                    <path d="M100 6v26M100 169v25M5 100h26M169 100h26M32 32l18 18m100 100 18 18M168 32l-18 18M50 150l-18 18" stroke="currentColor" stroke-width="1.4"/>
                                    <path d="m100 50 12 27 29 3-22 20 7 29-26-14-26 14 7-29-22-20 29-3 12-27Z" stroke="currentColor" stroke-width=".75" opacity=".65"/>
                                </svg>
                                <span class="char-ui-avatar"><?= kp_e($charInitial) ?></span>
                                <span class="char-ui-portrait-foot">KPAH · ANH HÙNG</span>
                            </div>
                            <div class="char-ui-card-content">
                                <div class="char-ui-identity">
                                    <div class="char-ui-identity-text">
                                        <span class="char-ui-caption">TÊN NHÂN VẬT</span>
                                        <h3><?= kp_e($charName) ?></h3>
                                        <p class="char-ui-clan">
                                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 1.8 16.4 5.2v7.2L10 18l-6.4-5.6V5.2L10 1.8Z M10 6v7m-3.5-3.5h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <?= $clanName !== '' ? kp_e($clanName) : 'Chưa gia nhập bang hội' ?>
                                        </p>
                                    </div>
                                    <div class="char-ui-level">
                                        <span>CẤP ĐỘ</span>
                                        <strong><?= (int) ($character['lastLv'] ?? 0) ?></strong>
                                    </div>
                                </div>
                                <dl class="char-ui-stats">
                                    <div class="char-ui-stat char-ui-stat--xp">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">✧</span> Kinh nghiệm</dt>
                                        <dd><?= $numberFormat($character['xp'] ?? 0) ?></dd>
                                    </div>
                                    <div class="char-ui-stat char-ui-stat--gold">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">●</span> Xu</dt>
                                        <dd><?= $numberFormat($character['gold'] ?? 0) ?></dd>
                                    </div>
                                    <div class="char-ui-stat char-ui-stat--silver">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">◆</span> Lượng</dt>
                                        <dd><?= $numberFormat($character['luong'] ?? 0) ?></dd>
                                    </div>
                                    <div class="char-ui-stat char-ui-stat--locked">
                                        <dt><span class="char-ui-stat-icon" aria-hidden="true">◇</span> Lượng khóa</dt>
                                        <dd><?= $numberFormat($character['luonglock'] ?? 0) ?></dd>
                                    </div>
                                </dl>
                                <div class="char-ui-card-bottom">
                                    <span class="char-ui-last">
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.2" stroke="currentColor" stroke-width="1.5"/><path d="M10 5.8v4.7l3.1 1.7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        <?= $lastSeen !== '' ? 'Đăng nhập gần nhất: ' . kp_e($lastSeen) : 'Chưa có lịch sử đăng nhập' ?>
                                    </span>
                                    <span class="char-ui-bottom-mark" aria-hidden="true">✦</span>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="community-dashboard" id="community" aria-labelledby="community-title">
        <div class="section-heading community-heading">
            <div>
                <p class="kicker">CỘNG ĐỒNG KPAH</p>
                <h2 id="community-title">Bảng xếp hạng</h2>
            </div>
            <p>Top cấp độ theo dữ liệu nhân vật KPAH.</p>
        </div>

        <div class="community-grid community-grid-single">
            <section class="community-panel community-ranking" aria-labelledby="ranking-title">
                <div class="community-panel-heading">
                    <div><span class="card-index">TOP 10</span><h3 id="ranking-title">Cấp độ nhân vật</h3></div>
                    <span class="community-panel-icon" aria-hidden="true">✦</span>
                </div>
                <?php if (!empty($topPlayers)): ?>
                    <div class="community-rank-table" role="table" aria-label="Top 10 cấp độ">
                        <div class="community-rank-header" role="row">
                            <span role="columnheader">Hạng</span>
                            <span role="columnheader">Nhân vật</span>
                            <span role="columnheader">Cấp</span>
                            <span role="columnheader">Kinh nghiệm</span>
                        </div>
                        <?php foreach ($topPlayers as $i => $player): ?>
                            <div class="community-rank-row" role="row">
                                <span class="community-rank rank-<?= (int) $i + 1 ?>" role="cell"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <strong class="community-rank-name" role="cell"><?= kp_e($player['charname'] ?? 'Nhân vật') ?></strong>
                                <span class="community-rank-power" role="cell"><?= (int) ($player['lv'] ?? 0) ?></span>
                                <strong class="community-rank-total" role="cell"><?= $numberFormat($player['exp'] ?? 0) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <p class="community-empty"><?= $rankingUnavailable ? 'Bảng xếp hạng đang tạm thời không tải được.' : 'Chưa có nhân vật trong bảng xếp hạng.' ?></p>
                <?php endif; ?>
            </section>
        </div>
    </section>

    <section class="account-section" id="profile" aria-labelledby="profile-title">
        <div class="section-heading">
            <div>
                <p class="kicker">THÔNG TIN TÀI KHOẢN</p>
                <h2 id="profile-title">Hồ sơ tài khoản</h2>
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

    <footer class="home-footer">
        <span class="footer-brand"><?= kp_e($appName) ?> <i>·</i> Tài khoản</span>
        <span class="footer-id">MÃ TÀI KHOẢN <?= kp_e($user['id']) ?></span>
        <a href="api/logout.php">ĐĂNG XUẤT <span aria-hidden="true">↗</span></a>
    </footer>
</main>

<script src="assets/js/user-home.js?v=2" defer></script>
</body>
</html>