<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin_security.php';
require_once __DIR__.'/../includes/admin_nav.php';
$admin=kp_admin_require(); // Admin ONLY; key is denied HTTP 403.
$csrf=kp_csrf_token();
$error='';$notice='';$users=[];
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 try {
  if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên xác nhận không hợp lệ.');
  list($allowed,$retry)=kp_admin_limit('key-user-update',(string)$admin['id'],15,3600);
  if(!$allowed)throw new RuntimeException('Bạn đã thao tác quá nhiều lần trong giờ.');
  $action=(string)($_POST['action']??'');
  if($action==='create'){
   $username=trim((string)($_POST['username']??''));
   $password=(string)($_POST['password']??'');
   if(!preg_match('/^[a-zA-Z0-9_.-]{4,32}$/D',$username))
    throw new RuntimeException('Tên đăng nhập phải dài 4–32 ký tự: chữ, số, _, -, dấu chấm.');
   if(strlen($password)<12||strlen($password)>128)
    throw new RuntimeException('Mật khẩu phải có 12–128 ký tự.');
   $hash=password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
   if(!is_string($hash))throw new RuntimeException('Không tạo được hash mật khẩu.');
   $st=kp_db()->prepare("INSERT INTO kpah_web_admins(username,password_hash,role,is_active) VALUES (?,?,'key',1)");
   $st->execute([$username,$hash]);
   $notice='Đã tạo tài khoản role key. Người dùng đăng nhập qua /quan-tri/dang-nhap.';
  }elseif($action==='disable'){
   $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
   if($id===false||$id<1)throw new RuntimeException('ID tài khoản không hợp lệ.');
   $st=kp_db()->prepare("UPDATE kpah_web_admins SET is_active=0 WHERE id=? AND role='key' AND is_active=1");
   $st->execute([$id]);
   if($st->rowCount()!==1)throw new RuntimeException('Tài khoản key không còn hoạt động.');
   $notice='Đã khóa tài khoản key.';
  }else throw new RuntimeException('Thao tác không hợp lệ.');
 }catch(PDOException $e){
  kp_log('key account SQL operation failed',$e);
  $error=((int)($e->errorInfo[1]??0)===1062)?'Tên đăng nhập đã tồn tại.':'Không cập nhật được tài khoản.';
 }catch(Throwable $e){
  kp_log('key account management failed',$e);
  $error=$e instanceof RuntimeException?$e->getMessage():'Không thực hiện được thao tác.';
 }
}
try{
 $users=kp_db()->query("SELECT id,username,is_active,created_at FROM kpah_web_admins WHERE role='key' ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){kp_log('key account listing failed',$e);$error='Không thể tải danh sách tài khoản key.';}
kp_send_security_headers(true);
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Quản lý role key · KPAH</title>
<link rel="stylesheet" href="/assets/css/topup.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/topup.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= (int)@filemtime(KP_ROOT.'/assets/css/admin-nav.css') ?>">
</head><body class="tp-page tp-admin"><main class="tp-content kp-key-users-content">
<?php kp_admin_nav('key-users','admin');?>
<section class="tp-hero"><span class="tp-kicker"><i></i> QUẢN TRỊ / PHÂN QUYỀN</span><h1>Tài khoản <em>role key.</em></h1><p class="tp-lead">Cấp quyền tạo mã giới thiệu nhưng không được xem giao dịch, ví, danh sách admin hoặc thay đổi cấu hình.</p></section>
<?php if($notice):?><div class="tp-alert good" role="status"><?=kp_e($notice)?></div><?php endif;?>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<section class="tp-section"><div class="tp-grid two"><div class="tp-panel"><h2>Tạo tài khoản key</h2>
<form method="post" class="tp-form" autocomplete="off"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="create">
<label class="tp-field"><span>Tên đăng nhập</span><input class="tp-input" name="username" minlength="4" maxlength="32" required autocomplete="off"></label>
<label class="tp-field"><span>Mật khẩu (ít nhất 12 ký tự)</span><input class="tp-input" name="password" type="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>
<p class="tp-muted">Mật khẩu được băm bằng bcrypt trước khi lưu; không ghi mật khẩu dạng rõ vào database hoặc log.</p>
<button class="tp-button" type="submit">Tạo role key ↗</button></form></div>
<div class="tp-panel"><h2>Danh sách role key</h2>
<?php if(!$users):?><p class="tp-muted">Chưa có tài khoản key nào.</p><?php else:?>
<div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Username</th><th>Ngày tạo</th><th>Trạng thái</th><th>Quản lý</th></tr></thead><tbody>
<?php foreach($users as $u):?><tr><td><?=kp_e($u['username'])?></td><td><?=kp_e($u['created_at'])?></td><td><?= (int)$u['is_active']?'Đang bật':'Đã khóa' ?></td><td><?php if((int)$u['is_active']):?>
<form method="post"><input type="hidden" name="csrf" value="<?=kp_e($csrf)?>"><input type="hidden" name="action" value="disable"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><button class="tp-button-secondary tp-btn--small" type="submit">Khóa quyền</button></form>
<?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></div></section>
</main></body></html>
