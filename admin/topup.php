<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

function kp_topup_admin_guard() {
    if (empty($_SESSION['kp_admin_id']) || ($_SESSION['kp_admin_role'] ?? '') !== 'admin'
        || empty($_SESSION['kp_admin_last_seen'])
        || time() - (int) $_SESSION['kp_admin_last_seen'] > 1800) {
        header('Location: login.php?expired=1'); exit;
    }
    $s = kp_db()->prepare('SELECT id FROM kpah_web_admins WHERE id=? AND role=? AND is_active=1 LIMIT 1');
    $s->execute([(int) $_SESSION['kp_admin_id'], 'admin']);
    if (!$s->fetchColumn()) {
        unset($_SESSION['kp_admin_id'], $_SESSION['kp_admin_role'], $_SESSION['kp_admin_last_seen']);
        header('Location: login.php'); exit;
    }
    $_SESSION['kp_admin_last_seen'] = time();
}
function kp_topup_int($raw, $max) {
    $raw = trim((string) $raw);
    if (!preg_match('/^[1-9][0-9]*$/D', $raw) || strlen($raw) > 12) {
        throw new RuntimeException('Số lượng phải là số nguyên dương.');
    }
    $n = (int) $raw;
    if ($n <= 0 || $n > $max) throw new RuntimeException('Số lượng vượt giới hạn cho phép.');
    return $n;
}
function kp_topup_lock(PDO $db, $lock) {
    $s=$db->prepare('SELECT GET_LOCK(?,5)'); $s->execute([$lock]);
    if ((int) $s->fetchColumn() !== 1) throw new RuntimeException('Nhân vật đang có yêu cầu khác, thử lại sau.');
}
function kp_topup_release(PDO $db, $lock) {
    if (!$lock) return;
    try { $s=$db->prepare('SELECT RELEASE_LOCK(?)'); $s->execute([$lock]); } catch (Throwable $ignored) {}
}
$names = ['gold'=>'Xu','luong'=>'Lượng không khóa','luonglock'=>'Lượng khóa'];
$notice = ''; $error = ''; $dbError = false; $account = null; $chars = []; $rates = []; $history = [];
$searchName = trim((string) ($_POST['username'] ?? $_GET['username'] ?? ''));
$csrf = kp_csrf_token();
try { kp_topup_admin_guard(); } catch (Throwable $e) { kp_log('topup admin guard failed',$e); http_response_code(503); exit('Không thể xác minh quyền quản trị.'); }

try {
    $db=kp_db();
    foreach ($db->query('SELECT currency,vnd_unit,units_per_vnd_unit FROM kpah_topup_rates')->fetchAll() as $r) $rates[$r['currency']]=$r;
    // Do not silently add rates at runtime; the migration must be explicitly imported.
    if (count($rates) !== 3) throw new RuntimeException('Chưa import database/topup_admin.sql hoặc thiếu cấu hình tỷ lệ.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!kp_csrf_check($_POST['csrf'] ?? null)) throw new RuntimeException('Phiên bảo mật đã hết hạn. Tải lại trang.');
        $action=(string) ($_POST['action'] ?? '');
        if ($action === 'rate') {
            $type=(string) ($_POST['currency'] ?? '');
            if (!isset($names[$type])) throw new RuntimeException('Loại tài nguyên không hợp lệ.');
            $units=(string) ($_POST['rate_units'] ?? '');
            if (!preg_match('/^(0|[1-9][0-9]*)$/D',$units) || strlen($units)>10 || (float)$units>1000000000) throw new RuntimeException('Tỷ lệ phải từ 0 đến 1 tỷ đơn vị mỗi 10.000đ.');
            $st=$db->prepare('UPDATE kpah_topup_rates SET units_per_vnd_unit=?,updated_by=? WHERE currency=?');
            $st->execute([(int)$units,(int)$_SESSION['kp_admin_id'],$type]);
            $rates[$type]['units_per_vnd_unit']=(int)$units;
            $notice='Đã lưu tỷ lệ '. $names[$type].'.';
        } elseif ($action === 'grant') {
            if (empty($_POST['offline_confirm']) || (string)$_POST['offline_confirm']!=='1') throw new RuntimeException('Cần xác nhận nhân vật đã thoát game trước khi cộng.');
            $type=(string)($_POST['currency'] ?? '');
            if (!isset($names[$type])) throw new RuntimeException('Tài nguyên không hợp lệ.');
            $charId=kp_topup_int($_POST['char_id'] ?? '',2147483647);
            $mode=(string)($_POST['mode'] ?? 'direct');
            $rawQty=kp_topup_int($_POST['quantity'] ?? '',1000000000);
            if ($mode==='vnd') {
                $rate=(int)$rates[$type]['units_per_vnd_unit'];
                // amount must be divisible by the reference currency; no arbitrary/hidden rounding.
                if ($rate<=0 || $rawQty%10000!==0) throw new RuntimeException('VNĐ phải là bội số của 10.000 và loại tài nguyên phải có tỷ lệ.');
                $quantity=(int)($rawQty/10000)*$rate;
                if ($quantity>1000000000) throw new RuntimeException('Số tài nguyên quy đổi vượt giới hạn.');
            } elseif ($mode==='direct') $quantity=$rawQty;
            else throw new RuntimeException('Chế độ cộng không hợp lệ.');
            if ($quantity<1) throw new RuntimeException('Số cộng phải lớn hơn 0.');
            $note=trim((string)($_POST['note'] ?? ''));
            if (function_exists('mb_substr')) $note=mb_substr($note,0,255,'UTF-8');
            else $note=substr($note,0,255);
            if (strlen($note)<5) throw new RuntimeException('Ghi lý do cộng (ít nhất 5 ký tự).');
            if (!preg_match('/^[0-9a-f]{32}$/D',(string)($_POST['request_key'] ?? ''))) throw new RuntimeException('Yêu cầu không hợp lệ. Tải lại trang.');
            $request=(string)$_POST['request_key'];
            $lock='kpah_topup_char_'.$charId;
            $locked=false;$ledgerInserted=false;$applied=false;
            try {
                kp_topup_lock($db,$lock); $locked=true;
                $s=$db->prepare('SELECT id,username FROM team_user WHERE username=? LIMIT 1');
                $s->execute([$searchName]);$owner=$s->fetch();
                if (!$owner) throw new RuntimeException('Không tìm thấy tài khoản.');
                $s=$db->prepare('SELECT id,charname,userId,gold,luong,luonglock,del,ban FROM tob_char WHERE id=? AND userId=? LIMIT 1');
                $s->execute([$charId,(int)$owner['id']]);$char=$s->fetch();
                if (!$char) throw new RuntimeException('Nhân vật không thuộc tài khoản đã nhập.');
                if ((int)$char['del']!==1 || (int)$char['ban']!==0) throw new RuntimeException('Nhân vật đang bị xóa/khóa, không thể cộng.');
                $before=(int)$char[$type];
                $limit=$type==='gold'?PHP_INT_MAX:4294967295;
                if ($before<0 || $quantity>$limit-$before) throw new RuntimeException('Vượt giới hạn số dư của game.');
                $after=$before+$quantity;
                // Write-ahead audit: MyISAM balances cannot join InnoDB transactions.
                // Any interruption after this record must be manually reconciled; never auto-replay.
                $s=$db->prepare('INSERT INTO kpah_topup_grants(request_key,admin_id,account_id,account_username,char_id,charname,currency,quantity,balance_before,balance_after,note,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$request,(int)$_SESSION['kp_admin_id'],(int)$owner['id'],(string)$owner['username'],$charId,(string)$char['charname'],$type,$quantity,$before,$after,$note,'processing']);
                $ledgerInserted=true;
                // Column identifier comes only from the hardcoded whitelist above.
                $sql='UPDATE tob_char SET '.$type.' = '.$type.' + ? WHERE id = ? AND userId = ? AND '.$type.' = ?';
                $s=$db->prepare($sql);$s->execute([$quantity,$charId,(int)$owner['id'],$before]);
                if ($s->rowCount()!==1) throw new RuntimeException('Số dư đã thay đổi hoặc nhân vật không còn hợp lệ. Kiểm tra thủ công.');
                $applied=true;
                $s=$db->prepare("UPDATE kpah_topup_grants SET status='applied',finished_at=NOW() WHERE request_key=? AND status='processing'");
                $s->execute([$request]);
                if ($s->rowCount()!==1) throw new RuntimeException('Đã cộng trong game DB nhưng ghi lịch sử thất bại. CẦN ĐỐI SOÁT.');
                $notice='Đã cộng '.number_format($quantity,0,',','.').' '.$names[$type].' cho '.$char['charname'].'. Số dư: '.number_format($before,0,',','.').' → '.number_format($after,0,',','.').'.';
                $_SESSION['kp_topup_last_request']=$request;
            } catch (Throwable $e) {
                if ($ledgerInserted) {
                    try {
                        $s=$db->prepare("UPDATE kpah_topup_grants SET status='needs_review',error_note=?,finished_at=NOW() WHERE request_key=? AND status='processing'");
                        $s->execute([$applied?'Đã ghi game DB, cần kiểm tra log':'Chưa xác định trạng thái ghi game DB',$request]);
                    } catch (Throwable $ignored) { kp_log('topup ledger state update failed',$ignored); }
                }
                if ($e instanceof PDOException) {
                    if ((int)($e->errorInfo[1] ?? 0)===1062) throw new RuntimeException('Yêu cầu này đã được xử lý hoặc đang chờ đối soát. Không bấm gửi lại.');
                    kp_log('topup manual grant failed',$e);
                    throw new RuntimeException($ledgerInserted?'Không rõ kết quả ghi tài nguyên. DỪNG cộng và kiểm tra nhật ký.':'Lỗi database. Không thể thực hiện.');
                }
                if ($ledgerInserted) throw new RuntimeException('Yêu cầu cần đối soát trong lịch sử. Không thử lại trước khi kiểm tra.');
                throw $e;
            } finally { if($locked) kp_topup_release($db,$lock); }
        } elseif ($action!=='lookup') throw new RuntimeException('Thao tác không hợp lệ.');
    }
    if ($searchName!=='') {
        if (strlen($searchName)>20) throw new RuntimeException('Tên tài khoản không hợp lệ.');
        $s=$db->prepare('SELECT id,username FROM team_user WHERE username=? LIMIT 1');
        $s->execute([$searchName]);$account=$s->fetch();
        if ($account) {
            $s=$db->prepare('SELECT id,charname,lastLv,gold,luong,luonglock,del,ban FROM tob_char WHERE userId=? ORDER BY id DESC LIMIT 30');
            $s->execute([(int)$account['id']]);$chars=$s->fetchAll();
        } else if (($_POST['action'] ?? '')==='lookup') $error='Không tìm thấy tài khoản.';
    }
    $history=$db->query("SELECT id,admin_id,account_username,charname,currency,quantity,balance_before,balance_after,status,note,created_at FROM kpah_topup_grants ORDER BY id DESC LIMIT 50")->fetchAll();
} catch (RuntimeException $e) {$error=$e->getMessage();}
catch (Throwable $e) { kp_log('topup admin page failed',$e); $error='Không thể kết nối phần quản lý nạp. Kiểm tra đã import database/topup_admin.sql.';$dbError=true; }
$csrf=kp_csrf_token();
$requestKey=bin2hex(random_bytes(16));
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Nạp tài nguyên · Quản trị KPAH</title>
<link rel="stylesheet" href="../assets/css/admin-topup.css?v=1"></head>
<body>
<header class="bar"><a href="index.php" class="brand">✦ KPAH <span>/ Quản trị nạp</span></a><nav><a href="index.php">Trang admin</a><a href="referrals.php">Mã giới thiệu</a><a href="logout.php">Đăng xuất</a></nav></header>
<main class="wrap">
<section class="hero"><div><p class="eyebrow">QUẢN LÝ TÀI NGUYÊN</p><h1>Cộng tài nguyên</h1><p>Cộng trực tiếp cho nhân vật khi đã thoát game. Mỗi thao tác có nhật ký riêng.</p></div><span class="state">THỦ CÔNG · CHƯA KẾT NỐI THANH TOÁN</span></section>
<?php if ($notice!==''): ?><div class="message good" role="status"><?= kp_e($notice) ?></div><?php endif; ?>
<?php if ($error!==''): ?><div class="message bad" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<div class="rate-grid">
<?php foreach($names as $code=>$label): $rate=$rates[$code] ?? null; ?>
<article class="rate-card"><span class="rate-name"><?= kp_e($label) ?></span><strong><?= $rate && (int)$rate['units_per_vnd_unit']>0 ? number_format((int)$rate['units_per_vnd_unit'],0,',','.') : 'Chưa đặt' ?></strong><p><?= $rate && (int)$rate['units_per_vnd_unit']>0 ? 'đơn vị / 10.000 VNĐ' : 'Chưa bật quy đổi VNĐ' ?></p>
<form method="post" class="rate-form"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="rate"><input type="hidden" name="currency" value="<?= kp_e($code) ?>">
<label for="rate-<?= kp_e($code) ?>">Tỷ lệ / 10.000đ</label><div class="inline"><input id="rate-<?= kp_e($code) ?>" name="rate_units" type="number" min="0" max="1000000000" step="1" value="<?= (int)($rate['units_per_vnd_unit'] ?? 0) ?>" required><button type="submit" <?= $dbError ? 'disabled' : '' ?>>Lưu</button></div></form></article>
<?php endforeach; ?></div>
<div class="panels">
<section class="panel"><div class="panel-top"><p class="eyebrow">BƯỚC 01</p><h2>Tìm tài khoản</h2></div>
<form method="post" class="search-form"><input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="lookup">
<label for="username">Tên đăng nhập game</label><div class="inline"><input id="username" name="username" maxlength="20" autocomplete="off" value="<?= kp_e($searchName) ?>" placeholder="Nhập đúng tên tài khoản" required><button type="submit">Tìm tài khoản</button></div></form>
<?php if($account): ?><p class="found">Đã tìm thấy <strong><?= kp_e($account['username']) ?></strong> · ID <?= (int)$account['id'] ?></p><?php endif; ?>
</section>
<section class="panel"><div class="panel-top"><p class="eyebrow">BƯỚC 02</p><h2>Cộng cho nhân vật</h2></div>
<?php if(!$account): ?><p class="muted">Tìm tài khoản trước để chọn nhân vật nhận tài nguyên.</p>
<?php elseif(!$chars): ?><p class="muted">Tài khoản này chưa có nhân vật trong tob_char.</p>
<?php else: ?>
<form method="post" id="grant-form">
<input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="grant"><input type="hidden" name="username" value="<?= kp_e($account['username']) ?>"><input type="hidden" name="request_key" value="<?= kp_e($requestKey) ?>">
<label for="char_id">Nhân vật nhận</label><select id="char_id" name="char_id" required>
<?php foreach($chars as $c): ?>
<option value="<?= (int)$c['id'] ?>" <?= ((int)$c['del']!==1 || (int)$c['ban']!==0)?'disabled':'' ?>><?= kp_e($c['charname']) ?> · Cấp <?= (int)$c['lastLv'] ?> · Xu <?= number_format((float)$c['gold'],0,',','.') ?> · Lượng <?= number_format((int)$c['luong'],0,',','.') ?> · Khóa <?= number_format((int)$c['luonglock'],0,',','.') ?><?= ((int)$c['del']!==1 || (int)$c['ban']!==0)?' (không khả dụng)':'' ?></option>
<?php endforeach; ?></select>
<label for="currency">Loại tài nguyên</label><select id="currency" name="currency" required><option value="gold">Xu</option><option value="luong">Lượng không khóa (Lượng thường)</option><option value="luonglock">Lượng khóa</option></select>
<label for="mode">Cách nhập</label><select id="mode" name="mode"><option value="direct">Nhập số tài nguyên cộng trực tiếp</option><option value="vnd">Nhập số tiền VNĐ để quy đổi</option></select>
<label for="quantity">Số lượng (hoặc số tiền VNĐ)</label><input id="quantity" type="number" name="quantity" min="1" max="1000000000" step="1" required placeholder="VD: 100">
<p class="hint">Quy đổi VNĐ chỉ hoạt động khi loại tài nguyên đã có tỷ lệ; số tiền cần chia hết cho 10.000đ.</p>
<label for="note">Lý do / mã tham chiếu</label><textarea id="note" name="note" minlength="5" maxlength="255" rows="2" required placeholder="VD: Admin cộng theo đơn hỗ trợ #..."></textarea>
<label class="check"><input type="checkbox" name="offline_confirm" value="1" required><span>Tôi đã kiểm tra nhân vật đã <strong>thoát game</strong>, đúng tài khoản, đúng số lượng. Tôi hiểu dữ liệu game có thể bị ghi đè nếu nhân vật còn online.</span></label>
<button type="submit" class="primary" id="grant-button" <?= $dbError ? 'disabled':'' ?>>Xác nhận cộng tài nguyên</button>
</form><?php endif; ?>
</section></div>
<section class="panel history"><div class="panel-top"><p class="eyebrow">ĐỐI SOÁT</p><h2>50 thao tác gần nhất</h2><p class="muted">Trạng thái <b>needs_review</b> phải kiểm tra số dư trong DB và log trước khi thực hiện yêu cầu mới.</p></div>
<div class="scroll"><table><thead><tr><th>ID</th><th>Thời gian</th><th>Admin</th><th>Tài khoản</th><th>Nhân vật</th><th>Loại</th><th>Đã cộng</th><th>Trước</th><th>Sau</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>
<?php foreach($history as $h): ?><tr><td>#<?= (int)$h['id'] ?></td><td><?= kp_e($h['created_at']) ?></td><td><?= (int)$h['admin_id'] ?></td><td><?= kp_e($h['account_username']) ?></td><td><?= kp_e($h['charname']) ?></td><td><?= kp_e($names[$h['currency']] ?? $h['currency']) ?></td><td><?= number_format((float)$h['quantity'],0,',','.') ?></td><td><?= number_format((float)$h['balance_before'],0,',','.') ?></td><td><?= number_format((float)$h['balance_after'],0,',','.') ?></td><td><span class="pill <?= kp_e($h['status']) ?>"><?= kp_e($h['status']) ?></span></td><td><?= kp_e($h['note']) ?></td></tr><?php endforeach; ?>
<?php if(!$history): ?><tr><td colspan="11">Chưa có lịch sử cộng tài nguyên.</td></tr><?php endif; ?></tbody></table></div></section>
<p class="foot">Không tự động cộng khi nạp thẻ hoặc chuyển khoản. Chỉ admin có quyền thực hiện. Game server có thể cache tài nguyên; cần đồng bộ logic game trước khi mở nạp tự động.</p>
</main>
<script src="../assets/js/admin-topup.js?v=1" defer></script>
</body></html>