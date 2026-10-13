<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/referrals.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

function kp_admin_home_guard()
{
    if (empty($_SESSION['kp_admin_id']) || ($_SESSION['kp_admin_role'] ?? '') !== 'admin') {
        header('Location: login.php');
        exit;
    }
    if (empty($_SESSION['kp_admin_last_seen']) || time() - (int) $_SESSION['kp_admin_last_seen'] > 1800) {
        unset($_SESSION['kp_admin_id'], $_SESSION['kp_admin_username'], $_SESSION['kp_admin_role'], $_SESSION['kp_admin_last_seen']);
        header('Location: login.php?expired=1');
        exit;
    }
    $st = kp_db()->prepare('SELECT role, is_active FROM kpah_web_admins WHERE id = ? LIMIT 1');
    $st->execute([(int) $_SESSION['kp_admin_id']]);
    $admin = $st->fetch();
    if (!$admin || $admin['role'] !== 'admin' || (int) $admin['is_active'] !== 1) {
        $_SESSION = [];
        header('Location: login.php');
        exit;
    }
    $_SESSION['kp_admin_last_seen'] = time();
}
kp_admin_home_guard();

$stats = ['accounts' => 0, 'codes' => 0, 'active_codes' => 0, 'uses' => 0, 'admins' => 0];
$recent = [];
$dbError = false;
try {
    $pdo = kp_db();
    $stats['accounts'] = (int) $pdo->query('SELECT COUNT(*) FROM team_user')->fetchColumn();
    $stats['codes'] = (int) $pdo->query('SELECT COUNT(*) FROM kpah_referral_codes')->fetchColumn();
    $stats['active_codes'] = (int) $pdo->query("SELECT COUNT(*) FROM kpah_referral_codes WHERE is_active = 1 AND used_count < `count` AND (`date` IS NULL OR `date` >= NOW())")->fetchColumn();
    $stats['uses'] = (int) $pdo->query('SELECT COUNT(*) FROM kpah_referral_code_uses')->fetchColumn();
    $stats['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM kpah_web_admins WHERE is_active = 1 AND role = 'admin'")->fetchColumn();
    $recent = $pdo->query('SELECT id, username, regdate FROM team_user ORDER BY id DESC LIMIT 8')->fetchAll();
} catch (Throwable $e) {
    kp_log('admin dashboard data load failed', $e);
    $dbError = true;
}
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Trang chủ quản trị · KPAH</title>
<link rel="stylesheet" href="../assets/css/admin-home.css?v=1">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><span class="brand-icon">✦</span><span><small>KPAH / ADMIN CONSOLE</small><strong>Control Center</strong></span></a>
  <div class="top-actions"><span class="secure"><i></i> Phiên bảo mật</span><span class="user"><?= kp_e($_SESSION['kp_admin_username'] ?? 'admin') ?></span><a class="logout" href="logout.php">Đăng xuất ↗</a></div>
</header>
<main class="shell">
  <section class="welcome">
    <div><div class="eyebrow">OVERVIEW / TỔNG QUAN</div><h1>Chào mừng trở lại.</h1><p>Mọi thông tin vận hành quan trọng của website KPAH, gói gọn trong một bảng điều khiển.</p></div>
    <div class="welcome-mark" aria-hidden="true"><span>✦</span><small>ADMIN<br>SPACE</small></div>
  </section>
  <?php if ($dbError): ?><div class="alert">Không thể tải một phần dữ liệu. Kiểm tra database và các bảng trong <code>database/referral_codes.sql</code>; chi tiết đã được ghi vào log máy chủ.</div><?php endif; ?>
  <section class="metrics" aria-label="Thống kê tổng quan">
    <article class="metric"><div class="metric-top"><span>Tài khoản game</span><span class="metric-icon">♙</span></div><strong><?= number_format($stats['accounts']) ?></strong><small>Tổng tài khoản trong team_user</small></article>
    <article class="metric"><div class="metric-top"><span>Mã giới thiệu</span><span class="metric-icon purple">⌘</span></div><strong><?= number_format($stats['codes']) ?></strong><small>Tổng mã đã tạo</small></article>
    <article class="metric"><div class="metric-top"><span>Mã đang khả dụng</span><span class="metric-icon green">↗</span></div><strong><?= number_format($stats['active_codes']) ?></strong><small>Còn lượt, chưa hết hạn và chưa khóa</small></article>
    <article class="metric"><div class="metric-top"><span>Lượt dùng mã</span><span class="metric-icon cyan">◷</span></div><strong><?= number_format($stats['uses']) ?></strong><small>Lịch sử sử dụng được ghi nhận</small></article>
  </section>
  <section class="section-heading"><div><div class="eyebrow">WORKSPACE</div><h2>Khu vực quản lý</h2><p>Chọn chức năng để tiếp tục công việc.</p></div><span class="admin-count"><?= number_format($stats['admins']) ?> admin đang bật</span></section>
  <section class="actions">
    <a class="action-card featured" href="topup.php"><span class="action-icon">✧</span><span class="action-copy"><small>TÀI NGUYÊN / 01</small><strong>Cộng Xu, Lượng, Lượng khóa</strong><span>Cộng thủ công theo nhân vật, thiết lập tỷ lệ quy đổi và xem lịch sử đối soát.</span><b>Mở quản lý nạp <span aria-hidden="true">↗</span></b></span></a>
    <a class="action-card" href="referrals.php"><span class="action-icon">⌘</span><span class="action-copy"><small>REGISTRATION / 01</small><strong>Quản lý mã giới thiệu</strong><span>Tạo mã, đặt hạn dùng, khóa/mở mã và theo dõi lịch sử đăng ký.</span><b>Mở quản lý <span aria-hidden="true">↗</span></b></span></a>
    <a class="action-card" href="../register.php" target="_blank" rel="noopener"><span class="action-icon cyan">↗</span><span class="action-copy"><small>PUBLIC WEBSITE / 02</small><strong>Trang đăng ký</strong><span>Mở giao diện đăng ký công khai trong tab mới để kiểm tra luồng người dùng.</span><b>Mở trang đăng ký <span aria-hidden="true">↗</span></b></span></a>
  </section>
  <section class="panel">
    <div class="panel-heading"><div><div class="eyebrow">LATEST ACTIVITY</div><h2>Tài khoản mới nhất</h2><p>Đọc dữ liệu trực tiếp từ bảng <code>team_user</code>.</p></div><span class="live">Live database</span></div>
    <div class="table-scroll"><table><thead><tr><th>ID</th><th>Tên tài khoản</th><th>Ngày đăng ký</th><th>Loại dữ liệu</th></tr></thead><tbody>
    <?php foreach ($recent as $row): ?><tr><td class="id">#<?= (int) $row['id'] ?></td><td class="username"><?= kp_e($row['username']) ?></td><td><?= kp_e($row['regdate'] ?? '—') ?></td><td><span class="tag">Game account</span></td></tr><?php endforeach; ?>
    <?php if (!$recent && !$dbError): ?><tr><td colspan="4" class="empty">Chưa có tài khoản để hiển thị.</td></tr><?php elseif (!$recent && $dbError): ?><tr><td colspan="4" class="empty">Dữ liệu chưa sẵn sàng. Kiểm tra cấu hình kết nối.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
  <footer><span>KPAH CONTROL CENTER</span><span>Đăng nhập được bảo vệ · Tự hết phiên sau 30 phút không hoạt động</span></footer>
</main>
</body></html>
