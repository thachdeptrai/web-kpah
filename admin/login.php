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
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(ellipse at top,#252044,#0b0a16 65%);color:#f7f5ff;font:15px system-ui,sans-serif}.card{width:min(100%,420px);padding:30px;border:1px solid #39334e;border-radius:20px;background:#151322;box-shadow:0 24px 70px #0007}h1{margin:0 0 8px;font-size:25px}p{color:#b7b2c9;line-height:1.6}label{display:block;margin:18px 0 7px}input{width:100%;padding:13px;border:1px solid #46405b;border-radius:10px;background:#0c0b15;color:#fff;font:inherit}button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:10px;background:#a78bfa;color:#171225;font-weight:800;font:inherit;cursor:pointer}.error{padding:12px;border-radius:10px;background:#481f30;color:#ffdbe3}.hint{font-size:13px}
</style></head><body><main class="card"><h1>Quản trị mã giới thiệu</h1><p>Đăng nhập bằng tài khoản có <b>role = admin</b>.</p>
<?php if ($error !== ''): ?><p class="error"><?= kp_e($error) ?></p><?php endif; ?>
<form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<label for="username">Tên đăng nhập</label><input id="username" name="username" maxlength="64" required autocomplete="username">
<label for="password">Mật khẩu</label><input id="password" name="password" type="password" required autocomplete="current-password">
<button type="submit">Đăng nhập</button></form><p class="hint">Nếu chưa có admin, tạo hash mật khẩu bằng lệnh PHP trong file SQL rồi thêm tài khoản vào bảng kpah_web_admins.</p></main></body></html>
