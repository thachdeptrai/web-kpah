<?php
require __DIR__.'/../includes/bootstrap.php';require_once __DIR__.'/../includes/topup_settings.php';
kp_send_security_headers(true);header('Content-Type: text/html; charset=utf-8');
try{kp_topup_require_admin();}catch(Throwable $e){kp_log('admin topup auth error',$e);http_response_code(503);exit('Không thể xác minh quyền admin.');}
$error='';$message='';$rates=kp_topup_rates();
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  if(!kp_csrf_check($_POST['csrf']??null)){$error='Phiên hết hạn. Tải lại trang.';}
  else{
    try{
      $next=[];foreach(kp_topup_defaults() as $k=>$v){
        $raw=(string)($_POST[$k]??'');
        if(!preg_match('/^(0|[1-9][0-9]*)$/D',$raw) || strlen($raw)>10 || (float)$raw>1000000000)throw new RuntimeException('Giá trị tỷ lệ không hợp lệ: '.$k);
        $next[$k]=(int)$raw;
      }
      kp_topup_save_rates($next);$rates=kp_topup_rates();$message='Đã lưu tỷ lệ. Trang nạp người chơi sẽ hiển thị tỷ lệ mới.';
    }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Không lưu được cấu hình. Kiểm tra quyền ghi thư mục storage.';kp_log('topup rates save failed',$e);}
  }
}
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tỷ lệ nạp · KPAH Admin</title><link rel="stylesheet" href="../assets/css/topup.css?v=1"></head><body><header><div class="inner"><a class="logo" href="topup.php">✦ KPAH <small>TỶ LỆ QUY ĐỔI</small></a><nav><a href="index.php">Admin</a><a href="topup.php">Quản lý nạp</a><a class="active" href="topup-rates.php">Tỷ lệ</a><a href="topup-queue.php">Dữ liệu nạp</a></nav></div></header><main class="wrap"><section class="hero"><span class="eyebrow">ADMIN / CẤU HÌNH</span><h1>Tỷ lệ nạp</h1><p>Đặt số Xu hoặc Lượng tương ứng với 10.000 VNĐ. Chỉ là tỷ lệ tính toán — không thay đổi số dư tài khoản game.</p></section><?php if($error!==''):?><div class="alert"><?=kp_e($error)?></div><?php endif;?><?php if($message!==''):?><div class="alert success"><?=kp_e($message)?></div><?php endif;?><section class="panel"><h2>Chỉnh tỷ lệ</h2><form method="post"><input type="hidden" name="csrf" value="<?=kp_e(kp_csrf_token())?>"><div class="form-grid"><div><label class="field" for="luong">Lượng thường / 10.000đ</label><input class="input" id="luong" name="luong" type="number" min="0" max="1000000000" step="1" required value="<?=$rates['luong']?>"></div><div><label class="field" for="luonglock">Lượng khóa / 10.000đ</label><input class="input" id="luonglock" name="luonglock" type="number" min="0" max="1000000000" step="1" required value="<?=$rates['luonglock']?>"></div><div><label class="field" for="gold">Xu / 10.000đ (0 = chưa bật)</label><input class="input" id="gold" name="gold" type="number" min="0" max="1000000000" step="1" required value="<?=$rates['gold']?>"></div></div><p class="fine">Không có gói giá cố định; số tiền bất kỳ được quy đổi theo tỷ lệ. Sửa cấu hình không tự cộng tài nguyên cho bất kỳ ai.</p><button class="btn" type="submit">Lưu tỷ lệ</button></form></section><div class="alert">Tỷ lệ lưu trong tệp riêng <code>storage/topup-rates.json</code> trên VPS, không phải SQL. Thư mục storage cần có quyền ghi của PHP, được chặn truy cập HTTP.</div></main></body></html>