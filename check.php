<?php
/**
 * Trang CHẨN ĐOÁN cài đặt – chỉ chạy được từ chính máy chủ (localhost).
 * Dùng khi đăng ký báo lỗi "Hệ thống đang bận hoặc bảo trì". Không ghi/sửa dữ liệu nào.
 * XOÁ FILE NÀY sau khi dùng xong, đặc biệt khi đưa lên hosting.
 */
require __DIR__ . '/includes/bootstrap.php';

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$rows = [];
function add(&$rows, $name, $ok, $detail = '')
{
    $rows[] = [$name, $ok, $detail];
}
function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* 1. PHP & extension */
add($rows, 'Phiên bản PHP', PHP_VERSION_ID >= 70200, PHP_VERSION . ' (cần >= 7.2)');
add($rows, 'Extension pdo_mysql', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'đã bật' : 'CHƯA bật: mở php.ini, bỏ dấu ; trước extension=pdo_mysql rồi restart Apache');
add($rows, 'Extension mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'đã bật' : 'chưa bật (không bắt buộc)');
add($rows, 'Extension gd (CAPTCHA PNG)', true, function_exists('imagecreatetruecolor') ? 'đã bật' : 'chưa bật – đang dùng CAPTCHA SVG dự phòng, không sao');

/* 2. Cấu hình đang dùng */
$c = kp_config('db');
add($rows, 'Cấu hình DB đang dùng', true,
    'host=' . $c['host'] . ' | port=' . $c['port'] . ' | database=' . $c['name'] . ' | user=' . $c['user'] . ' | mật khẩu=' . ($c['pass'] === '' ? '(rỗng)' : '(đã đặt)'));
add($rows, 'File config.register.local.php', is_file(KP_ROOT . '/config.register.local.php'), is_file(KP_ROOT . '/config.register.local.php') ? 'có' : 'KHÔNG có – chưa điền user/mật khẩu DB');

/* 3. Kết nối DB */
$pdo = null;
if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = kp_db();
        $v = $pdo->query('SELECT VERSION()')->fetchColumn();
        add($rows, 'Kết nối database', true, 'thành công – MySQL/MariaDB ' . $v);
    } catch (\Throwable $e) {
        $code = (string) $e->getCode();
        $hint = '';
        if ($code === '1045') {
            $hint = 'Sai user/mật khẩu. XAMPP mặc định là user root, mật khẩu rỗng. Sửa trong config.register.local.php.';
        } elseif ($code === '1049') {
            $hint = 'Không có database tên "' . $c['name'] . '". Hãy import kpah.sql hoặc sửa lại "name" cho đúng tên database trong phpMyAdmin.';
        } elseif ($code === '2002') {
            $hint = 'Không kết nối được tới MySQL. Kiểm tra MySQL đã Start trong XAMPP chưa, và cổng (port) có phải ' . $c['port'] . ' không (có máy dùng 3307).';
        }
        add($rows, 'Kết nối database', false, $e->getMessage() . ($hint ? '  =>  ' . $hint : ''));

        // Gợi ý: tìm database nào đang chứa bảng team_user
        if ($code === '1049') {
            try {
                $tmp = new PDO(sprintf('mysql:host=%s;port=%d', $c['host'], (int) $c['port']), (string) $c['user'], (string) $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $found = $tmp->query("SELECT DISTINCT table_schema FROM information_schema.tables WHERE table_name = 'team_user'")->fetchAll(PDO::FETCH_COLUMN);
                add($rows, 'Database chứa bảng team_user', !empty($found), $found ? implode(', ', $found) . '  =>  đặt "name" bằng tên này' : 'Không có database nào chứa team_user – hãy import kpah.sql');
            } catch (\Throwable $e2) {
                // bỏ qua
            }
        }
    }
}

/* 4. Bảng team_user */
if ($pdo) {
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM `team_user`')->fetchAll(PDO::FETCH_COLUMN);
        add($rows, 'Bảng team_user', true, 'có ' . count($cols) . ' cột');
        $need = ['userid', 'provider', 'agent', 'username', 'password', 'phone', 'regdate', 'ban', 'fromgame', 'email'];
        $miss = array_diff($need, $cols);
        add($rows, 'Đủ cột cần ghi', !$miss, $miss ? 'THIẾU: ' . implode(', ', $miss) : 'đủ');

        $idx = $pdo->query('SHOW INDEX FROM `team_user`')->fetchAll();
        $uniq = [];
        foreach ($idx as $i) {
            if ((int) $i['Non_unique'] === 0) {
                $uniq[] = $i['Column_name'];
            }
        }
        add($rows, 'Ràng buộc UNIQUE username / userid', in_array('username', $uniq, true) && in_array('userid', $uniq, true), 'unique trên: ' . implode(', ', array_unique($uniq)));

        $r = $pdo->query('SELECT COUNT(*) AS n, COALESCE(MAX(userid),0) AS m FROM `team_user`')->fetch();
        add($rows, 'Dữ liệu hiện có', true, $r['n'] . ' tài khoản, userid lớn nhất = ' . $r['m']);
    } catch (\Throwable $e) {
        add($rows, 'Bảng team_user', false, $e->getMessage() . '  =>  chưa import kpah.sql vào đúng database?');
    }

    try {
        $st = $pdo->prepare('SELECT GET_LOCK(?, 2)');
        $st->execute(['kpah_check_lock']);
        $got = (int) $st->fetchColumn() === 1;
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute(['kpah_check_lock']);
        add($rows, 'GET_LOCK (cấp userid an toàn)', $got, $got ? 'hoạt động' : 'không lấy được khoá');
    } catch (\Throwable $e) {
        add($rows, 'GET_LOCK (cấp userid an toàn)', false, $e->getMessage());
    }

    try {
        $g = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
        add($rows, 'Quyền của user DB', true, implode(' | ', $g));
    } catch (\Throwable $e) {
        // bỏ qua
    }
}

/* 5. Mã hoá mật khẩu & thư mục ghi */
$expect = '*6BB4837EB74329105EE4568DDA7DC67ED2CA2AD9';
add($rows, 'Băm mật khẩu tương thích', kp_game_password_hash('123456') === $expect, kp_game_password_hash('123456'));
$dir = kp_rate_dir();
add($rows, 'Thư mục giới hạn tần suất ghi được', $dir !== '', $dir !== '' ? $dir : 'không ghi được storage/ratelimit (giới hạn tần suất sẽ bị bỏ qua)');
add($rows, 'Session', session_status() === PHP_SESSION_ACTIVE, session_save_path() ?: '(mặc định)');
$errlog = ini_get('error_log');
add($rows, 'File log lỗi PHP', true, $errlog ? $errlog : 'mặc định của Apache (XAMPP: C:\\xampp\\apache\\logs\\error.log)');

?><!DOCTYPE html>
<html lang="vi"><head><meta charset="utf-8"><title>KPAH – Chẩn đoán</title>
<meta name="robots" content="noindex">
<style>
body{font:15px/1.5 system-ui,Segoe UI,Arial,sans-serif;background:#0b0a16;color:#ece8fb;margin:0;padding:24px}
h1{font-size:20px;margin:0 0 4px} p{color:#aaa6cd;margin:0 0 18px}
table{border-collapse:collapse;width:100%;max-width:980px}
td{padding:9px 12px;border-bottom:1px solid #2a2650;vertical-align:top}
td:first-child{width:260px;font-weight:600}
.ok{color:#4fd6a0}.bad{color:#ff8a7a;font-weight:700}
code{word-break:break-all}
</style></head><body>
<h1>KPAH – Chẩn đoán cài đặt</h1>
<p>Chỉ chạy trên localhost, không sửa dữ liệu. Hãy xoá file <code>check.php</code> khi xong.</p>
<table>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r[0]) ?></td><td class="<?= $r[1] ? 'ok' : 'bad' ?>"><?= $r[1] ? 'OK' : 'LỖI' ?> <span style="font-weight:400;color:#ece8fb"><code><?= h($r[2]) ?></code></span></td></tr>
<?php endforeach; ?>
</table>
</body></html>
