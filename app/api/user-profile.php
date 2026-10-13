<?php
require __DIR__ . '/../includes/bootstrap.php';
kp_send_security_headers(false);
header('Content-Type: application/json; charset=utf-8');
function kp_profile_reply(array $payload, $status = 200) { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
function kp_profile_code_valid($prefix, $code, $userId, $email) {
    $keys = [$prefix.'_hash',$prefix.'_expiry',$prefix.'_target',$prefix.'_user'];
    foreach ($keys as $key) if (!isset($_SESSION[$key])) return false;
    return (int)$_SESSION[$prefix.'_user'] === $userId
        && time() <= (int)$_SESSION[$prefix.'_expiry']
        && hash_equals((string)$_SESSION[$prefix.'_target'], $email)
        && preg_match('/^\d{6}$/', $code) === 1
        && hash_equals((string)$_SESSION[$prefix.'_hash'], hash('sha256', $code));
}

function kp_profile_email_html($heading, $intro, $code, $minutes) {
    $communityUrl = 'https://zalo.me/g/zmyedl5en7sldmd9el1z';
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeMinutes = htmlspecialchars((string)$minutes, ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Support KPAH</title></head>'
      .'<body style="margin:0;padding:0;background:#f6f8fa;font-family:Arial,Helvetica,sans-serif;color:#24292f;">'
      .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f8fa;padding:28px 12px;"><tr><td align="center">'
      .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border:1px solid #d8dee4;border-radius:12px;overflow:hidden;">'
      .'<tr><td style="padding:22px 28px;border-bottom:1px solid #d8dee4;"><div style="font-size:22px;font-weight:700;color:#24292f;">Support KPAH</div><div style="font-size:12px;color:#656d76;margin-top:5px;">BẢO MẬT TÀI KHOẢN</div></td></tr>'
      .'<tr><td style="padding:28px;"><h1 style="font-size:22px;line-height:1.35;margin:0 0 18px;">'.$safeHeading.'</h1>'
      .'<p style="font-size:15px;line-height:1.65;margin:0 0 22px;">'.$safeIntro.'</p>'
      .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f8fa;border:1px solid #d8dee4;border-radius:10px;"><tr><td align="center" style="padding:22px 12px;">'
      .'<div style="font-size:11px;font-weight:700;letter-spacing:1.2px;color:#656d76;">MÃ XÁC MINH CỦA BẠN</div>'
      .'<div style="font-size:34px;line-height:1.5;font-weight:700;letter-spacing:8px;color:#0969da;padding-left:8px;">'.$safeCode.'</div>'
      .'<div style="font-size:12px;color:#656d76;">Mã có hiệu lực trong '.$safeMinutes.' phút</div></td></tr></table>'
      .'<p style="font-size:13px;line-height:1.65;color:#57606a;margin:20px 0 0;">Chỉ nhập mã này trên website chính thức. Không chia sẻ mã cho bất kỳ ai. Nếu bạn không yêu cầu thao tác này, hãy bảo vệ tài khoản và liên hệ quản trị viên.</p>'
      .'<div style="height:1px;background:#d8dee4;margin:24px 0;"></div><p style="font-size:14px;font-weight:700;margin:0 0 8px;">Cần hỗ trợ?</p>'
      .'<p style="font-size:14px;line-height:1.6;color:#57606a;margin:0 0 16px;">Tham gia cộng đồng KPAH trên Zalo để nhận hỗ trợ và cập nhật.</p>'
      .'<table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#0969da" style="border-radius:6px;"><a href="'.$communityUrl.'" style="display:inline-block;padding:11px 16px;color:#fff;text-decoration:none;font-size:14px;font-weight:700;">Tham gia cộng đồng Zalo</a></td></tr></table>'
      .'</td></tr><tr><td style="padding:18px 28px;background:#f6f8fa;border-top:1px solid #d8dee4;font-size:12px;line-height:1.6;color:#656d76;">Support KPAH · Email tự động, vui lòng không trả lời.<br>Nếu nút không mở được: <a href="'.$communityUrl.'" style="color:#0969da;word-break:break-all;">'.$communityUrl.'</a></td></tr>'
      .'</table></td></tr></table></body></html>';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); kp_profile_reply(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405); }
if (empty($_SESSION['kp_user_id'])) kp_profile_reply(['ok'=>false,'message'=>'Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.'],401);
if (!kp_csrf_check($_POST['csrf'] ?? '')) kp_profile_reply(['ok'=>false,'message'=>'Phiên bảo mật hết hạn. Tải lại trang rồi thử lại.'],403);
$action=(string)($_POST['action'] ?? 'update'); $userId=(int)$_SESSION['kp_user_id'];
$email=strtolower(trim((string)($_POST['email'] ?? ''))); $oldCode=trim((string)($_POST['old_email_code'] ?? '')); $newCode=trim((string)($_POST['email_code'] ?? ''));
try {
 $db=kp_db(); $st=$db->prepare('SELECT email FROM team_user WHERE id = ? LIMIT 1'); $st->execute([$userId]); $current=$st->fetch();
 if (!$current) kp_profile_reply(['ok'=>false,'message'=>'Không tìm thấy tài khoản.'],404);
 $oldEmail=strtolower(trim((string)$current['email']));
 if ($action==='send_old_email_code') {
  if ($oldEmail==='' || !filter_var($oldEmail,FILTER_VALIDATE_EMAIL)) kp_profile_reply(['ok'=>false,'message'=>'Tài khoản chưa có email hiện tại hợp lệ. Hãy liên hệ quản trị viên.'],422);
  list($allowed,$retry)=kp_rate_limit('profile_old_email_code',$userId.'|'.kp_client_ip(),3,600,true);
  if (!$allowed) kp_profile_reply(['ok'=>false,'message'=>'Bạn yêu cầu mã Gmail cũ quá nhiều lần. Hãy thử lại sau ít phút.'],429);
  $code=(string)random_int(100000,999999); $ttl=max(60,(int)kp_config('registration.email_code_ttl',600));
  $oldPlain = "Mã xác minh để thay đổi email tài khoản KPAH: ".$code."\nMã có hiệu lực trong ".(int)ceil($ttl/60)." phút.\nChỉ nhập mã này trên website chính thức. Nếu bạn không yêu cầu thay đổi email, hãy đổi mật khẩu và liên hệ quản trị viên.\nCộng đồng KPAH: https://zalo.me/g/zmyedl5en7sldmd9el1z";
  kp_send_mail_smtp($oldEmail,'Xác minh bảo mật tài khoản KPAH',$oldPlain,kp_profile_email_html('Xác minh thay đổi email','Bạn hoặc ai đó vừa yêu cầu thay đổi email tài khoản KPAH. Dùng mã này để xác minh email hiện tại.',$code,(int)ceil($ttl/60)));
  $_SESSION['kp_profile_old_email_hash']=hash('sha256',$code); $_SESSION['kp_profile_old_email_expiry']=time()+$ttl; $_SESSION['kp_profile_old_email_target']=$oldEmail; $_SESSION['kp_profile_old_email_user']=$userId;
  unset($_SESSION['kp_profile_old_email_verified_email'],$_SESSION['kp_profile_old_email_verified_expiry'],$_SESSION['kp_profile_email_hash'],$_SESSION['kp_profile_email_expiry'],$_SESSION['kp_profile_email_target'],$_SESSION['kp_profile_email_user']);
  kp_profile_reply(['ok'=>true,'message'=>'Đã gửi mã đến Gmail đang liên kết. Nhập mã này rồi mới được yêu cầu mã Gmail mới.']);
 }
 if ($action==='send_email_code') {
  $firstLink = $oldEmail==='';
  if (!$email || strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) kp_profile_reply(['ok'=>false,'message'=>'Hãy nhập email mới hợp lệ.'],422);
  if ($oldEmail===$email) kp_profile_reply(['ok'=>false,'message'=>'Email mới phải khác email đang liên kết.'],422);
  $dupe=$db->prepare('SELECT 1 FROM team_user WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1'); $dupe->execute([$email,$userId]);
  if ($dupe->fetchColumn()) kp_profile_reply(['ok'=>false,'message'=>'Email này đã được tài khoản khác sử dụng.'],409);
  if (!$firstLink) {
  list($verifyAllowed,$verifyRetry)=kp_rate_limit('profile_old_email_verify',$userId.'|'.kp_client_ip(),8,600,true);
  if (!$verifyAllowed) kp_profile_reply(['ok'=>false,'message'=>'Bạn nhập mã Gmail cũ quá nhiều lần. Hãy thử lại sau ít phút.'],429);
  if (!kp_profile_code_valid('kp_profile_old_email',$oldCode,$userId,$oldEmail)) kp_profile_reply(['ok'=>false,'message'=>'Mã Gmail hiện tại không đúng hoặc đã hết hạn. Hãy gửi lại mã Gmail cũ.'],422);
  if (!$email || strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) kp_profile_reply(['ok'=>false,'message'=>'Hãy nhập Gmail mới hợp lệ.'],422);
  if ($oldEmail===$email) kp_profile_reply(['ok'=>false,'message'=>'Gmail mới phải khác Gmail đang liên kết.'],422);
  $dupe=$db->prepare('SELECT 1 FROM team_user WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1'); $dupe->execute([$email,$userId]);
  if ($dupe->fetchColumn()) kp_profile_reply(['ok'=>false,'message'=>'Email này đã được tài khoản khác sử dụng.'],409);
  $_SESSION['kp_profile_old_email_verified_email']=$oldEmail; $_SESSION['kp_profile_old_email_verified_expiry']=time()+600;
  unset($_SESSION['kp_profile_old_email_hash'],$_SESSION['kp_profile_old_email_expiry'],$_SESSION['kp_profile_old_email_target'],$_SESSION['kp_profile_old_email_user']);
  }
  list($allowed,$retry)=kp_rate_limit('profile_email_code',$userId.'|'.kp_client_ip(),4,600,true);
  if (!$allowed) kp_profile_reply(['ok'=>false,'message'=>'Bạn yêu cầu mã Gmail mới quá nhiều lần. Hãy thử lại sau ít phút.'],429);
  $code=(string)random_int(100000,999999); $ttl=max(60,(int)kp_config('registration.email_code_ttl',600));
  $newPlain = "Mã xác minh để liên kết Gmail mới với tài khoản KPAH: ".$code."\nMã có hiệu lực trong ".(int)ceil($ttl/60)." phút.\nChỉ nhập mã này trên website chính thức. Nếu bạn không yêu cầu thay đổi email, hãy bỏ qua thư này.\nCộng đồng KPAH: https://zalo.me/g/zmyedl5en7sldmd9el1z";
  kp_send_mail_smtp($email,'Mã xác minh thay đổi email KPAH',$newPlain,kp_profile_email_html('Xác minh email mới','Bạn vừa yêu cầu liên kết địa chỉ email mới với tài khoản KPAH. Nhập mã bên dưới để tiếp tục.',$code,(int)ceil($ttl/60)));
  $_SESSION['kp_profile_email_hash']=hash('sha256',$code); $_SESSION['kp_profile_email_expiry']=time()+$ttl; $_SESSION['kp_profile_email_target']=$email; $_SESSION['kp_profile_email_user']=$userId;
  kp_profile_reply(['ok'=>true,'message'=>$firstLink ? 'Đã gửi mã tới email muốn liên kết. Nhập mã rồi bấm Lưu thay đổi.' : 'Đã xác minh Gmail cũ và gửi mã đến Gmail mới. Nhập mã mới rồi bấm Lưu thay đổi.']);
 }
 if ($action!=='update') kp_profile_reply(['ok'=>false,'message'=>'Yêu cầu không hợp lệ.'],400);
 $phone=trim((string)($_POST['phone'] ?? ''));
 if ($email!=='' && (!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)) kp_profile_reply(['ok'=>false,'message'=>'Địa chỉ email không hợp lệ.'],422);
 if ($phone!=='' && !preg_match('/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/',$phone)) kp_profile_reply(['ok'=>false,'message'=>'Số điện thoại chưa đúng định dạng. Dùng 0xxxxxxxxx hoặc +84xxxxxxxxx.'],422);
 if ($email!==$oldEmail) {
  if ($email==='') kp_profile_reply(['ok'=>false,'message'=>'Không thể gỡ email đã liên kết bằng thao tác này.'],422);
  $verifiedOld=isset($_SESSION['kp_profile_old_email_verified_email'],$_SESSION['kp_profile_old_email_verified_expiry']) && time()<=(int)$_SESSION['kp_profile_old_email_verified_expiry'] && hash_equals((string)$_SESSION['kp_profile_old_email_verified_email'],$oldEmail);
  if ($oldEmail!=='' && !$verifiedOld) kp_profile_reply(['ok'=>false,'message'=>'Bạn phải xác minh mã gửi đến Gmail hiện tại trước khi đổi email.'],403);
  if (!kp_profile_code_valid('kp_profile_email',$newCode,$userId,$email)) kp_profile_reply(['ok'=>false,'message'=>'Gmail mới chưa được xác minh hoặc mã đã hết hạn. Hãy xác minh cả Gmail cũ và Gmail mới.'],422);
  $dupe=$db->prepare('SELECT 1 FROM team_user WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1'); $dupe->execute([$email,$userId]);
  if ($dupe->fetchColumn()) kp_profile_reply(['ok'=>false,'message'=>'Email này đã được tài khoản khác sử dụng.'],409);
 }
 $up=$db->prepare('UPDATE team_user SET email = ?, phone = ? WHERE id = ?'); $up->execute([$email,$phone,$userId]);
 if ($email!==$oldEmail) unset($_SESSION['kp_profile_old_email_verified_email'],$_SESSION['kp_profile_old_email_verified_expiry'],$_SESSION['kp_profile_email_hash'],$_SESSION['kp_profile_email_expiry'],$_SESSION['kp_profile_email_target'],$_SESSION['kp_profile_email_user']);
 kp_profile_reply(['ok'=>true,'message'=>'Đã lưu thông tin tài khoản.','email'=>$email,'phone'=>$phone]);
} catch (Throwable $e) { kp_log('cập nhật hồ sơ user thất bại',$e); kp_profile_reply(['ok'=>false,'message'=>'Không thể xử lý yêu cầu lúc này. Hãy thử lại sau.'],503); }
