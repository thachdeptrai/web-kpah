<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/referrals.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');

function kp_admin_require_role()
{
    if (empty($_SESSION['kp_admin_id']) || ($_SESSION['kp_admin_role'] ?? '') !== 'admin') {
        header('Location: login.php');
        exit;
    }
    if (empty($_SESSION['kp_admin_last_seen']) || time() - (int) $_SESSION['kp_admin_last_seen'] > 1800) {
        unset($_SESSION['kp_admin_id'], $_SESSION['kp_admin_username'], $_SESSION['kp_admin_role']);
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['kp_admin_last_seen'] = time();
    $st = kp_db()->prepare('SELECT role, is_active FROM kpah_web_admins WHERE id = ? LIMIT 1');
    $st->execute([(int) $_SESSION['kp_admin_id']]);
    $row = $st->fetch();
    if (!$row || $row['role'] !== 'admin' || (int) $row['is_active'] !== 1) {
        unset($_SESSION['kp_admin_id'], $_SESSION['kp_admin_username'], $_SESSION['kp_admin_role']);
        header('Location: login.php');
        exit;
    }
}
kp_admin_require_role();

$notice = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!kp_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Phiên đã hết hạn. Tải lại trang rồi thử lại.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $rawCode = strtoupper(trim((string) ($_POST['code'] ?? '')));
                $limit = filter_var($_POST['count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
                $dateRaw = trim((string) ($_POST['date'] ?? ''));
                if ($rawCode === '') {
                    $rawCode = strtoupper(bin2hex(random_bytes(5)));
                }
                if (!preg_match('/^[A-Z0-9_-]{4,64}$/', $rawCode)) {
                    throw new RuntimeException('Mã chỉ gồm A-Z, 0-9, gạch dưới hoặc gạch ngang; dài 4–64 ký tự.');
                }
                if ($limit === false) {
                    throw new RuntimeException('Số lượt tối đa phải từ 1 đến 100000.');
                }
                $expiry = null;
                if ($dateRaw !== '') {
                    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dateRaw);
                    if (!$dt || $dt->format('Y-m-d\TH:i') !== $dateRaw) {
                        throw new RuntimeException('Ngày hết hạn không hợp lệ.');
                    }
                    $expiry = $dt->format('Y-m-d H:i:s');
                    if (strtotime($expiry) <= time()) {
                        throw new RuntimeException('Ngày hết hạn phải ở tương lai.');
                    }
                }
                $st = kp_db()->prepare('INSERT INTO kpah_referral_codes (code, `count`, used_count, `date`, created_by) VALUES (?, ?, 0, ?, ?)');
                $st->execute([$rawCode, (int) $limit, $expiry, (int) $_SESSION['kp_admin_id']]);
                $notice = 'Đã tạo mã ' . $rawCode . '.';
            } elseif ($action === 'toggle') {
                $id = (int) ($_POST['id'] ?? 0);
                $st = kp_db()->prepare('UPDATE kpah_referral_codes SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
                $st->execute([$id]);
                $notice = 'Đã cập nhật trạng thái mã.';
            } else {
                throw new RuntimeException('Thao tác không hợp lệ.');
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
                $error = 'Mã này đã tồn tại. Hãy nhập mã khác hoặc để trống để tạo mã tự động.';
            } else {
                kp_log('admin referral action thất bại', $e);
                $error = 'Không thể cập nhật dữ liệu. Kiểm tra schema và log máy chủ.';
            }
        }
    }
}
$csrf = kp_csrf_token();
$codes = kp_db()->query(
    'SELECT c.id, c.code, c.`count`, c.used_count, c.`date`, c.is_active, c.created_at,
            GREATEST(c.`count` - c.used_count, 0) AS remaining,
            (SELECT COUNT(*) FROM kpah_referral_code_uses u WHERE u.referral_id = c.id) AS logged_uses
     FROM kpah_referral_codes c ORDER BY c.id DESC LIMIT 500'
)->fetchAll();
$uses = kp_db()->query('SELECT code, username, used_at FROM kpah_referral_code_uses ORDER BY id DESC LIMIT 100')->fetchAll();
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý mã giới thiệu KPAH</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#0c0b15;color:#f4f1ff;font:14px system-ui,sans-serif}header{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:22px max(20px,calc((100% - 1180px)/2));border-bottom:1px solid #302b42;background:#151322}h1{font-size:23px;margin:0}h2{font-size:17px;margin:0 0 16px}a{color:#c4b5fd}.wrap{max-width:1180px;margin:26px auto;padding:0 18px}.card{padding:22px;margin-bottom:20px;background:#151322;border:1px solid #302b42;border-radius:16px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}label{display:block;color:#c6c0d8;font-size:13px;margin-bottom:6px}input{width:100%;padding:11px;border:1px solid #46405b;border-radius:9px;background:#0c0b15;color:#fff;font:inherit}button{padding:10px 13px;border:0;border-radius:9px;background:#a78bfa;color:#171225;font-weight:750;cursor:pointer}button.secondary{background:#342d4a;color:#f5f3ff}.submit{margin-top:14px}.notice,.error{padding:12px;border-radius:10px;margin-bottom:16px}.notice{background:#153b31;color:#c9ffeb}.error{background:#481f30;color:#ffdbe3}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;white-space:nowrap}th,td{text-align:left;padding:11px;border-bottom:1px solid #302b42}th{color:#c6c0d8;font-size:12px;text-transform:uppercase}code{color:#d8ccff}.muted{color:#aaa4be}.pill{display:inline-block;padding:4px 8px;border-radius:99px;background:#253a31;color:#c9ffdf}.off{background:#482632;color:#ffdbe3}.stats{display:flex;gap:8px;flex-wrap:wrap}.stats span{background:#242037;border-radius:8px;padding:6px 9px}@media(max-width:700px){.grid{grid-template-columns:1fr}header{align-items:flex-start}h1{font-size:18px}.card{padding:15px}}
</style></head><body><header><h1>Quản lý mã giới thiệu</h1><div>Xin chào <?= kp_e($_SESSION['kp_admin_username'] ?? 'admin') ?> · <a href="logout.php">Đăng xuất</a></div></header>
<main class="wrap">
<?php if ($notice): ?><div class="notice"><?= kp_e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="error"><?= kp_e($error) ?></div><?php endif; ?>
<section class="card"><h2>Tạo mã mới</h2><p class="muted">Để trống mã để tự sinh mã ngẫu nhiên. Số lượt là tổng lượt đăng ký tối đa; ngày hết hạn để trống nghĩa là không hết hạn.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="create">
<div class="grid"><div><label for="code">Mã giới thiệu (tuỳ chọn)</label><input id="code" name="code" maxlength="64" placeholder="VD: KPAH2026"></div><div><label for="count">Tổng lượt được dùng</label><input id="count" name="count" type="number" min="1" max="100000" value="1" required></div><div><label for="date">Hạn sử dụng (để trống = vô hạn)</label><input id="date" name="date" type="datetime-local"></div></div><button class="submit" type="submit">Tạo mã giới thiệu</button></form></section>
<section class="card"><h2>Danh sách mã</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Mã</th><th>Đã dùng</th><th>Tối đa</th><th>Còn lại</th><th>Hạn sử dụng</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($codes as $c): $expired = !empty($c['date']) && strtotime($c['date']) < time(); $active = (int)$c['is_active'] === 1 && !$expired && (int)$c['remaining'] > 0; ?>
<tr><td><?= (int)$c['id'] ?></td><td><code><?= kp_e($c['code']) ?></code></td><td><?= (int)$c['used_count'] ?></td><td><?= (int)$c['count'] ?></td><td><?= (int)$c['remaining'] ?></td><td><?= $c['date'] ? kp_e($c['date']) : 'Không hết hạn' ?></td><td><span class="pill <?= $active ? '' : 'off' ?>"><?= $active ? 'Đang dùng' : ($expired ? 'Hết hạn' : ((int)$c['remaining'] < 1 ? 'Hết lượt' : 'Đã khóa')) ?></span></td><td><form method="post"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="secondary" type="submit"><?= (int)$c['is_active'] === 1 ? 'Khóa mã' : 'Mở mã' ?></button></form></td></tr>
<?php endforeach; if (!$codes): ?><tr><td colspan="8" class="muted">Chưa có mã giới thiệu nào.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="card"><h2>Lịch sử sử dụng gần đây</h2><div class="table-wrap"><table><thead><tr><th>Mã</th><th>Tài khoản đăng ký</th><th>Thời điểm sử dụng</th></tr></thead><tbody>
<?php foreach ($uses as $u): ?><tr><td><code><?= kp_e($u['code']) ?></code></td><td><?= kp_e($u['username']) ?></td><td><?= kp_e($u['used_at']) ?></td></tr><?php endforeach; if (!$uses): ?><tr><td colspan="3" class="muted">Chưa có lượt sử dụng.</td></tr><?php endif; ?>
</tbody></table></div></section></main></body></html>
