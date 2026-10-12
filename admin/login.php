<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!empty($_SESSION['kp_admin_id']) && ($_SESSION['kp_admin_role'] ?? '') === 'admin') {
    header('Location: referrals.php');
    exit;
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!kp_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Phiên làm việc hết hạn. Tải lại trang rồi thử lại.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        try {
            $st = kp_db()->prepare('SELECT id, username, password_hash, role, is_active FROM kpah_web_admins WHERE username = ? LIMIT 1');
            $st->execute([$username]);
            $admin = $st->fetch();
            if ($admin && (int) $admin['is_active'] === 1 && $admin['role'] === 'admin' && password_verify($password, (string) $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['kp_admin_id'] = (int) $admin['id'];
                $_SESSION['kp_admin_username'] = (string) $admin['username'];
                $_SESSION['kp_admin_role'] = (string) $admin['role'];
                $_SESSION['kp_admin_last_seen'] = time();
                header('Location: referrals.php');
                exit;
            }
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản không có quyền admin.';
        } catch (Throwable $e) {
            kp_log('admin login thất bại', $e);
            $error = 'Chưa thể đăng nhập. Hãy kiểm tra đã import database/referral_codes.sql chưa.';
        }
    }
}
$csrf = kp_csrf_token();
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng nhập quản trị KPAH</title>
<link rel="stylesheet" href="../assets/css/admin-login.css?v=1"></head><body><main class="card"><div class="mark" aria-hidden="true">✦</div><div class="eyebrow">KPAH • SECURE ACCESS</div><h1>Chào mừng trở lại.</h1><p>Đăng nhập vào trung tâm điều khiển để quản lý mã giới thiệu và hoạt động đăng ký.</p>
<?php if ($error !== ''): ?><p class="error"><?= kp_e($error) ?></p><?php endif; ?>
<form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<label for="username">Tên đăng nhập</label><input id="username" name="username" maxlength="64" required autocomplete="username">
<label for="password">Mật khẩu</label><input id="password" name="password" type="password" required autocomplete="current-password">
<button type="submit">Đăng nhập an toàn <span aria-hidden="true">↗</span></button></form><div class="secure"><i></i> Kết nối quản trị · Phiên tự hết hạn sau 30 phút</div><div class="footer">KPAH CONTROL CENTER · AUTHORIZED PERSONNEL ONLY</div></main></body></html>
