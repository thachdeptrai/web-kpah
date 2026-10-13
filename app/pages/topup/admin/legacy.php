<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/topup_core.php';
require_once __DIR__.'/../_layout.php';
$admin=kp_topup_admin();$search=trim((string)($_GET['username']??''));$board=[];$created=[];$error='';
if($search!=='' && (strlen($search)>45 || !preg_match('/^[a-zA-Z0-9_.-]{1,45}$/D',$search)))$error='Tên tài khoản không hợp lệ.';
else{
    try{
        $db=kp_db();
        $sql='SELECT id,username,xu,luong,luongKhoa,ve,svID FROM board_naptien';
        if($search!=='')$sql.=' WHERE username=?';
        $st=$db->prepare($sql.' ORDER BY id DESC LIMIT 50');$st->execute($search!==''?[$search]:[]);$board=$st->fetchAll();
        $sql='SELECT id,username,xu,luong,luongK,ve,svID FROM board_created';
        if($search!=='')$sql.=' WHERE username=?';
        $st=$db->prepare($sql.' ORDER BY id DESC LIMIT 50');$st->execute($search!==''?[$search]:[]);$created=$st->fetchAll();
    }catch(Throwable $e){kp_log('legacy topup queue query failed',$e);$error='Không đọc được dữ liệu nạp cũ. Kiểm tra kết nối SQL.';}
}
kp_topup_page_start('Bảng nạp hiện có','legacy',(string)$admin['username'],true);?>
<section class="tp-hero"><span class="tp-kicker"><i></i> ADMIN / DỮ LIỆU GAME</span><h1>Kiểm tra <em>bảng nạp.</em></h1><p class="tp-lead">Đọc trực tiếp dữ liệu game từ hai bảng nạp sẵn có. Trang này chỉ xem, không thêm, sửa hoặc xóa một dòng nào trong SQL.</p></section>
<?php if($error): ?><div class="tp-alert bad" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">BỘ LỌC</span><h2>Tìm theo username</h2></div></div>
<form method="get" class="tp-actions"><input class="tp-input tp-flex-1" name="username" maxlength="45" value="<?= kp_e($search) ?>" placeholder="Tài khoản game, để trống xem tất cả"><button class="tp-button" type="submit">Tìm dữ liệu ↗</button></form></div></section>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">BẢNG GỐC</span><h2>board_naptien</h2></div><span class="tp-chip"><?= count($board) ?> dòng</span></div>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>ID</th><th>Username</th><th>Xu</th><th>Lượng</th><th>Lượng khóa</th><th>Vé</th><th>Server</th></tr></thead><tbody>
<?php foreach($board as $r): ?><tr><td>#<?= (int)$r['id'] ?></td><td><?= kp_e($r['username']) ?></td><td><?= number_format((float)$r['xu'],0,',','.') ?></td><td><?= number_format((float)$r['luong'],0,',','.') ?></td><td><?= number_format((float)$r['luongKhoa'],0,',','.') ?></td><td><?= (int)$r['ve'] ?></td><td><?= (int)$r['svID'] ?></td></tr><?php endforeach; ?>
<?php if(!$board): ?><tr><td colspan="7">Không có dữ liệu phù hợp.</td></tr><?php endif; ?></tbody></table></div></div></section>
<section class="tp-section"><div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">BẢNG GỐC</span><h2>board_created</h2></div><span class="tp-chip"><?= count($created) ?> dòng</span></div>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>ID</th><th>Username</th><th>Xu</th><th>Lượng</th><th>Lượng khóa</th><th>Vé</th><th>Server</th></tr></thead><tbody>
<?php foreach($created as $r): ?><tr><td>#<?= (int)$r['id'] ?></td><td><?= kp_e($r['username']) ?></td><td><?= number_format((float)$r['xu'],0,',','.') ?></td><td><?= number_format((float)$r['luong'],0,',','.') ?></td><td><?= number_format((float)$r['luongK'],0,',','.') ?></td><td><?= (int)$r['ve'] ?></td><td><?= (int)$r['svID'] ?></td></tr><?php endforeach; ?>
<?php if(!$created): ?><tr><td colspan="7">Không có dữ liệu phù hợp.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="tp-alert warning">Dữ liệu trong bảng chưa chứng minh server game xử lý giao dịch như thế nào. Muốn bật cấp tự động cần tìm và kiểm tra đoạn code game đọc các bảng này.</div>
</section><?php kp_topup_page_end(); ?>
