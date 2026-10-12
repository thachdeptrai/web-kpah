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
*{box-sizing:border-box} :root{color-scheme:dark;--line:rgba(172,184,226,.18);--muted:#98a3c0;--violet:#a89aff;--cyan:#72e8e5}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;overflow:hidden;background:radial-gradient(ellipse at 18% 0%,#303365 0,transparent 42%),radial-gradient(ellipse at 95% 90%,#154a54 0,transparent 35%),#080b14;color:#f5f7ff;font:15px/1.6 Inter,"Be Vietnam Pro",system-ui,sans-serif}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(#ffffff08 1px,transparent 1px),linear-gradient(90deg,#ffffff08 1px,transparent 1px);background-size:40px 40px;mask-image:radial-gradient(ellipse at center,#000,transparent 80%)}
.card{position:relative;width:min(100%,450px);padding:clamp(25px,6vw,42px);border:1px solid var(--line);border-radius:25px;background:linear-gradient(145deg,#171d34eF,#101523f2);box-shadow:0 30px 100px #0007,0 0 80px #8b7dff0c;backdrop-filter:blur(24px)}
.mark{width:54px;height:54px;display:grid;place-items:center;border:1px solid #a89aff70;border-radius:17px;background:linear-gradient(145deg,#8a7bff,#26365f);font-size:25px;box-shadow:0 12px 32px #8a7bff30;margin-bottom:26px}
.eyebrow{color:var(--cyan);font-size:10px;font-weight:850;letter-spacing:.22em;text-transform:uppercase}
h1{font-size:30px;line-height:1.15;letter-spacing:-.045em;margin:8px 0 10px}p{color:var(--muted);line-height:1.7;margin:0 0 22px}.secure{display:flex;align-items:center;gap:8px;color:#a5f2d2;font-size:12px;margin:20px 0 0}.secure i{width:7px;height:7px;background:#6fe5b5;border-radius:50%;box-shadow:0 0 12px #6fe5b5}
label{display:block;margin:18px 0 7px;color:#d5dbee;font-size:12px;font-weight:650}input{width:100%;padding:14px;border:1px solid #3b4562;border-radius:12px;background:#090e1b;color:#fff;font:inherit;outline:none;transition:border .2s,box-shadow .2s}input:focus{border-color:var(--violet);box-shadow:0 0 0 3px #a89aff20}button{width:100%;margin-top:24px;padding:14px;border:0;border-radius:12px;background:linear-gradient(115deg,#b9adff,#77e8e5);color:#0a1020;font:inherit;font-weight:850;cursor:pointer;transition:transform .2s,filter .2s}button:hover{transform:translateY(-1px);filter:brightness(1.08)}.error{padding:12px 14px;border-radius:12px;background:#ff6d8b12;border:1px solid #ff6d8b45;color:#ffc0cd;font-size:13px;margin:18px 0}.hint{font-size:12px;margin:18px 0 0}.footer{margin-top:24px;text-align:center;color:#66718e;font-size:10px;letter-spacing:.1em}
</style></head><body><main class="card"><div class="mark" aria-hidden="true">✦</div><div class="eyebrow">KPAH • SECURE ACCESS</div><h1>Chào mừng trở lại.</h1><p>Đăng nhập vào trung tâm điều khiển để quản lý mã giới thiệu và hoạt động đăng ký.</p>
<?php if ($error !== ''): ?><p class="error"><?= kp_e($error) ?></p><?php endif; ?>
<form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>">
<label for="username">Tên đăng nhập</label><input id="username" name="username" maxlength="64" required autocomplete="username">
<label for="password">Mật khẩu</label><input id="password" name="password" type="password" required autocomplete="current-password">
<button type="submit">Đăng nhập an toàn <span aria-hidden="true">↗</span></button></form><div class="secure"><i></i> Kết nối quản trị · Phiên tự hết hạn sau 30 phút</div><div class="footer">KPAH CONTROL CENTER · AUTHORIZED PERSONNEL ONLY</div></main></body></html>
