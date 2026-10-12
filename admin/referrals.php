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
:root{color-scheme:dark;--bg:#080b14;--panel:rgba(18,24,42,.82);--line:rgba(166,183,230,.15);--text:#f4f7ff;--muted:#929db9;--violet:#9b8cff;--cyan:#61e4e8;--green:#66e3b0;--red:#ff829e}
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(ellipse at 12% -10%,#283061 0,transparent 36%),radial-gradient(ellipse at 100% 20%,#163a48 0,transparent 30%),var(--bg);color:var(--text);font:14px/1.55 Inter,"Be Vietnam Pro",system-ui,sans-serif}
body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.15;background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:36px 36px;mask-image:linear-gradient(#000,transparent 80%)}
header{position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:20px;padding:17px max(20px,calc((100% - 1320px)/2));border-bottom:1px solid var(--line);background:rgba(8,11,20,.78);backdrop-filter:blur(22px)}
.brand{display:flex;align-items:center;gap:13px}.brand-mark{width:42px;height:42px;display:grid;place-items:center;border:1px solid #9b8cff70;border-radius:14px;background:linear-gradient(145deg,#6d5de8,#25325b);box-shadow:0 0 30px #8070ff30;font-size:21px}.brand-copy{display:grid}.eyebrow{font-size:10px;letter-spacing:.22em;text-transform:uppercase;color:var(--cyan);font-weight:800}.brand h1{font-size:18px;letter-spacing:-.03em;margin:0}.head-right{display:flex;align-items:center;gap:14px;color:var(--muted)}a{color:#b9b0ff;text-decoration:none}a:hover{color:#fff}.logout{border:1px solid var(--line);padding:8px 12px;border-radius:10px;background:#ffffff06}
.wrap{position:relative;max-width:1320px;margin:30px auto;padding:0 22px 56px}.hero{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:24px}.hero h2{font-size:clamp(25px,4vw,38px);line-height:1.1;letter-spacing:-.045em;margin:7px 0}.hero p{color:var(--muted);margin:0;max-width:680px}.live{display:flex;align-items:center;gap:8px;color:#b8ffe3;border:1px solid #66e3b02e;background:#66e3b00b;padding:9px 12px;border-radius:99px;white-space:nowrap;font-size:12px}.live:before{content:"";width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 12px var(--green)}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.stat{position:relative;overflow:hidden;padding:19px;border:1px solid var(--line);border-radius:17px;background:linear-gradient(145deg,#19213aab,#111727c9);box-shadow:0 18px 45px #0002}.stat:after{content:"";position:absolute;right:-24px;top:-32px;width:100px;height:100px;border-radius:50%;background:var(--glow,#9b8cff);filter:blur(48px);opacity:.18}.stat-label{color:var(--muted);font-size:12px}.stat-value{font-size:29px;font-weight:800;letter-spacing:-.04em;margin:7px 0 0;font-variant-numeric:tabular-nums}.stat-note{font-size:11px;color:#a6b2d1}
.card{padding:23px;margin-bottom:17px;border:1px solid var(--line);border-radius:19px;background:linear-gradient(145deg,rgba(20,27,47,.92),rgba(12,17,30,.92));box-shadow:0 18px 55px #0002;backdrop-filter:blur(10px)}.card-head{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:17px}.card h2{font-size:16px;letter-spacing:-.02em;margin:0}.section-kicker{font-size:10px;text-transform:uppercase;letter-spacing:.18em;color:var(--muted);margin-bottom:5px}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:1.3fr .8fr 1.1fr;gap:13px}label{display:block;color:#c4cce2;font-size:12px;margin-bottom:7px}input{width:100%;padding:12px 13px;border:1px solid #39435e;border-radius:11px;background:#090e1a;color:#fff;font:inherit;outline:none;transition:border .2s,box-shadow .2s}input:focus{border-color:var(--violet);box-shadow:0 0 0 3px #9b8cff1d}input::placeholder{color:#59647e}button{padding:11px 15px;border:1px solid transparent;border-radius:11px;background:linear-gradient(120deg,#b2a6ff,#76e2e5);color:#0b1020;font-weight:800;cursor:pointer;font:inherit;transition:transform .18s,filter .18s}button:hover{filter:brightness(1.08);transform:translateY(-1px)}button.secondary{background:#ffffff07;border-color:var(--line);color:#d8ddf0}.submit{margin-top:15px}.notice,.error{padding:13px 15px;border-radius:12px;margin-bottom:16px;border:1px solid transparent}.notice{background:#66e3b00e;border-color:#66e3b033;color:#a9ffda}.error{background:#ff829e0d;border-color:#ff829e38;color:#ffc1cf}.table-tools{display:flex;align-items:center;gap:10px}.search{max-width:280px}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}table{width:100%;border-collapse:collapse;white-space:nowrap}th,td{text-align:left;padding:13px 14px;border-bottom:1px solid #ffffff0c}th{color:#8390af;font-size:10px;letter-spacing:.12em;text-transform:uppercase;background:#ffffff04}tbody tr{transition:background .15s}tbody tr:hover{background:#ffffff04}tbody tr:last-child td{border-bottom:0}code{color:#d4ccff;font-weight:800;letter-spacing:.04em}.pill{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:99px;background:#66e3b012;color:#8cf0c6;border:1px solid #66e3b02b;font-size:11px}.off{background:#ff829e0d;color:#ffb1c0;border-color:#ff829e25}.mini{font-size:11px;color:var(--muted)}.empty{text-align:center;padding:28px;color:var(--muted)}footer{padding:18px 0;color:#65708d;font-size:11px;display:flex;justify-content:space-between}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:1fr 1fr}.grid>div:last-child{grid-column:1/-1}.hero{align-items:flex-start;flex-direction:column}}
@media(max-width:600px){header{position:relative;padding:14px 16px;align-items:flex-start}.brand h1{font-size:15px}.head-right{font-size:12px;gap:8px}.wrap{padding:0 13px 30px;margin-top:22px}.stats{gap:9px}.stat{padding:14px}.stat-value{font-size:25px}.card{padding:15px;border-radius:15px}.grid{grid-template-columns:1fr}.grid>div:last-child{grid-column:auto}.card-head{align-items:flex-start;flex-direction:column}.search{max-width:none;width:100%}footer{gap:10px;flex-direction:column}}
</style></head><body><header><div class="brand"><div class="brand-mark">✦</div><div class="brand-copy"><span class="eyebrow">KPAH • CONTROL CENTER</span><h1>Quản trị hệ thống</h1></div></div><div class="head-right"><span><?= kp_e($_SESSION['kp_admin_username'] ?? 'admin') ?></span><a class="logout" href="logout.php">Đăng xuất ↗</a></div></header>
<main class="wrap">
<div class="hero"><div><div class="eyebrow">REFERRAL MANAGEMENT / 01</div><h2>Trung tâm mã giới thiệu</h2><p>Quản lý lượt đăng ký, hạn sử dụng và lịch sử kích hoạt trong một bảng điều khiển duy nhất.</p></div><div class="live">Phiên quản trị được bảo vệ</div></div>
<section class="stats">
<div class="stat" style="--glow:#9b8cff"><div class="stat-label">Tổng mã đã tạo</div><div class="stat-value"><?= number_format(count($codes)) ?></div><div class="stat-note">Tối đa 500 mã gần nhất</div></div>
<div class="stat" style="--glow:#66e3b0"><div class="stat-label">Mã đang hoạt động</div><div class="stat-value"><?= number_format(count(array_filter($codes, function($x){ return (int)$x['is_active'] === 1 && (int)$x['remaining'] > 0 && (empty($x['date']) || strtotime($x['date']) >= time()); }))) ?></div><div class="stat-note">Còn lượt và chưa hết hạn</div></div>
<div class="stat" style="--glow:#61e4e8"><div class="stat-label">Lượt sử dụng đã ghi nhận</div><div class="stat-value"><?= number_format(array_sum(array_map(function($x){ return (int)$x['used_count']; }, $codes))) ?></div><div class="stat-note">Tổng trên danh sách đang tải</div></div>
<div class="stat" style="--glow:#ff829e"><div class="stat-label">Lịch sử gần đây</div><div class="stat-value"><?= number_format(count($uses)) ?></div><div class="stat-note">100 lượt mới nhất</div></div>
</section>
<?php if ($notice): ?><div class="notice"><?= kp_e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="error"><?= kp_e($error) ?></div><?php endif; ?>
<section class="card"><div class="card-head"><div><div class="section-kicker">CREATE / REFERRAL</div><h2>Tạo mã giới thiệu mới</h2></div><span class="mini">Cấp mã có giới hạn</span></div><p class="muted">Để trống mã để tự sinh mã ngẫu nhiên. Số lượt là tổng lượt đăng ký tối đa; ngày hết hạn để trống nghĩa là không hết hạn.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="create">
<div class="grid"><div><label for="code">Mã giới thiệu (tuỳ chọn)</label><input id="code" name="code" maxlength="64" placeholder="VD: KPAH2026"></div><div><label for="count">Tổng lượt được dùng</label><input id="count" name="count" type="number" min="1" max="100000" value="1" required></div><div><label for="date">Hạn sử dụng (để trống = vô hạn)</label><input id="date" name="date" type="datetime-local"></div></div><button class="submit" type="submit">Tạo mã giới thiệu</button></form></section>
<section class="card"><div class="card-head"><div><div class="section-kicker">INVENTORY / CODES</div><h2>Danh sách mã giới thiệu</h2></div><input class="search" id="code-search" type="search" placeholder="Tìm mã hoặc trạng thái…" aria-label="Tìm mã giới thiệu"></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Mã</th><th>Đã dùng</th><th>Tối đa</th><th>Còn lại</th><th>Hạn sử dụng</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($codes as $c): $expired = !empty($c['date']) && strtotime($c['date']) < time(); $active = (int)$c['is_active'] === 1 && !$expired && (int)$c['remaining'] > 0; ?>
<tr><td><?= (int)$c['id'] ?></td><td><code><?= kp_e($c['code']) ?></code></td><td><?= (int)$c['used_count'] ?></td><td><?= (int)$c['count'] ?></td><td><?= (int)$c['remaining'] ?></td><td><?= $c['date'] ? kp_e($c['date']) : 'Không hết hạn' ?></td><td><span class="pill <?= $active ? '' : 'off' ?>"><?= $active ? 'Đang dùng' : ($expired ? 'Hết hạn' : ((int)$c['remaining'] < 1 ? 'Hết lượt' : 'Đã khóa')) ?></span></td><td><form method="post"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="secondary" type="submit"><?= (int)$c['is_active'] === 1 ? 'Khóa mã' : 'Mở mã' ?></button></form></td></tr>
<?php endforeach; if (!$codes): ?><tr><td colspan="8" class="muted">Chưa có mã giới thiệu nào.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="card"><div class="card-head"><div><div class="section-kicker">AUDIT / ACTIVITY</div><h2>Lịch sử sử dụng gần đây</h2></div><span class="mini">Nhật ký sử dụng mã</span></div><div class="table-wrap"><table><thead><tr><th>Mã</th><th>Tài khoản đăng ký</th><th>Thời điểm sử dụng</th></tr></thead><tbody>
<?php foreach ($uses as $u): ?><tr><td><code><?= kp_e($u['code']) ?></code></td><td><?= kp_e($u['username']) ?></td><td><?= kp_e($u['used_at']) ?></td></tr><?php endforeach; if (!$uses): ?><tr><td colspan="3" class="muted">Chưa có lượt sử dụng.</td></tr><?php endif; ?>
</tbody></table></div></section><footer><span>KPAH CONTROL CENTER · ADMIN ACCESS ONLY</span><span>Session timeout: 30 phút · Dữ liệu cập nhật khi tải lại</span></footer></main>
<script>
(function(){var q=document.getElementById('code-search');if(!q)return;q.addEventListener('input',function(){var term=q.value.toLowerCase();document.querySelectorAll('section.card tbody tr').forEach(function(row){if(row.closest('section').querySelector('#code-search')||row.parentElement.parentElement.parentElement.querySelector('#code-search')){row.style.display=row.textContent.toLowerCase().includes(term)?'':'none';}});});})();
</script></body></html>
