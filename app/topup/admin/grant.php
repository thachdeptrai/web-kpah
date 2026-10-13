<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/topup_core.php';
require_once __DIR__ . '/../_layout.php';
$admin=kp_topup_admin();$csrf=kp_csrf_token();$rates=kp_topup_rates();
$error='';$notice='';$owner=null;$chars=[];
$search=trim((string)($_POST['username']??$_GET['username']??''));
$names=['gold'=>'Xu','luong'=>'Lượng thường','luonglock'=>'Lượng khóa'];
if (strlen($search)>20) $search='';
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    try {
        if (!kp_admin_csrf_valid($_POST['csrf']??null)) throw new RuntimeException('Phiên làm việc đã hết hạn. Tải lại trang.');
        if (($_POST['action']??'')==='grant') {
            list($allowed,$retry)=kp_rate_limit('kp_admin_grant',(string)$admin['id'],30,3600,true);
            if (!$allowed) throw new RuntimeException('Đã đạt giới hạn thao tác. Thử lại sau.');
            if ((string)($_POST['offline']??'')!=='1') throw new RuntimeException('Phải xác nhận nhân vật đã thoát game.');
            $currency=(string)($_POST['currency']??'');
            if (!isset($names[$currency])) throw new RuntimeException('Loại tài nguyên không hợp lệ.');
            $charId=filter_var($_POST['char_id']??null,FILTER_VALIDATE_INT);
            if ($charId===false || $charId<1) throw new RuntimeException('Chưa chọn đúng nhân vật.');
            $requestId=(string)($_POST['request_id']??'');
            if (!preg_match('/^[a-f0-9]{32}$/D',$requestId)) throw new RuntimeException('Mã yêu cầu không hợp lệ. Tải lại trang.');
            $amountRaw=trim((string)($_POST['quantity']??''));
            if (!preg_match('/^[1-9][0-9]*$/D',$amountRaw) || strlen($amountRaw)>10 || (float)$amountRaw>1000000000) {
                throw new RuntimeException('Số lượng phải từ 1 đến 1 tỷ.');
            }
            $entered=(int)$amountRaw;
            $mode=(string)($_POST['mode']??'direct');
            if ($mode==='vnd') $units=kp_topup_units($entered,(int)$rates[$currency]);
            elseif ($mode==='direct') $units=$entered;
            else throw new RuntimeException('Cách nhập không hợp lệ.');
            $note=trim((string)($_POST['note']??''));
            if (function_exists('mb_strlen')?mb_strlen($note,'UTF-8')<5:strlen($note)<5) throw new RuntimeException('Nhập lý do cộng ít nhất 5 ký tự.');
            if (function_exists('mb_substr')) $note=mb_substr($note,0,200,'UTF-8'); else $note=substr($note,0,200);
            if ($search==='') throw new RuntimeException('Chưa nhập tài khoản game.');
            $db=kp_db();
            $charLock=kp_topup_root().'/character-'.(int)$charId.'.lock';
            $fh=@fopen($charLock,'c+');
            if (!$fh || !flock($fh,LOCK_EX)) throw new RuntimeException('Không khóa được thao tác trên nhân vật.');
            try {
                $st=$db->prepare('SELECT id,username,ban FROM team_user WHERE username=? LIMIT 1');
                $st->execute([$search]);$acc=$st->fetch();
                if (!$acc || (int)$acc['ban']!==0) throw new RuntimeException('Tài khoản không tồn tại hoặc đang bị khóa.');
                $st=$db->prepare('SELECT id,charname,userId,gold,luong,luonglock,del,ban FROM tob_char WHERE id=? AND userId=? LIMIT 1');
                $st->execute([(int)$charId,(int)$acc['id']]);$char=$st->fetch();
                if (!$char) throw new RuntimeException('Nhân vật không thuộc tài khoản này.');
                if ((int)$char['del']!==1 || (int)$char['ban']!==0) throw new RuntimeException('Nhân vật đã bị xóa hoặc khóa.');
                foreach(kp_topup_recent('grants',null,100000) as $g) {
                    if ((int)($g['char_id']??0)===(int)$charId
                        && in_array(($g['status']??''),['processing','needs_review'],true)) {
                        throw new RuntimeException('Nhân vật có thao tác chưa đối soát. Không cộng tiếp.');
                    }
                }
                $beforeRaw=(string)$char[$currency];
                if (!ctype_digit($beforeRaw) || strlen($beforeRaw)>18) throw new RuntimeException('Số dư quá lớn, cần xử lý thủ công.');
                $before=(int)$beforeRaw;
                $max=$currency==='gold'?PHP_INT_MAX:4294967295;
                if ($before<0 || $units>$max-$before) throw new RuntimeException('Cộng vượt giới hạn số dư game.');
                $after=$before+$units;
                $grant=[
                    'id'=>$requestId,'admin_id'=>(int)$admin['id'],'user_id'=>(int)$acc['id'],
                    'username'=>(string)$acc['username'],'char_id'=>(int)$charId,'charname'=>(string)$char['charname'],
                    'currency'=>$currency,'units'=>$units,'before'=>$before,'after'=>$after,
                    'mode'=>$mode,'entered_amount'=>$entered,'note'=>$note,
                    'status'=>'processing','created_at'=>time(),'updated_at'=>time()
                ];
                // Durable write-ahead record; MyISAM does not participate in transactions.
                kp_topup_with_record('grants',$requestId,function($old,$path) use($grant){
                    if ($old!==null) throw new RuntimeException('Mã yêu cầu đã xử lý. Không nhấn gửi lại.');
                    kp_topup_write_file($path,$grant);
                });
                // Identifiers originate only from our hardcoded currency whitelist.
                try {
                    $sql='UPDATE tob_char SET '.$currency.'='.$currency.'+? WHERE id=? AND userId=? AND '.$currency.'=? AND del=1 AND ban=0';
                    $st=$db->prepare($sql);
                    $st->execute([$units,(int)$charId,(int)$acc['id'],$before]);
                    if ($st->rowCount()!==1) throw new RuntimeException('Số dư đã thay đổi. Dừng và đối soát.');
                } catch (Throwable $e) {
                    kp_log('manual grant outcome uncertain',$e);
                    kp_topup_with_record('grants',$requestId,function($old,$path){
                        if ($old && $old['status']==='processing') {
                            $old['status']='needs_review';$old['updated_at']=time();kp_topup_write_file($path,$old);
                        }
                    });
                    throw new RuntimeException('Yêu cầu cần đối soát trước khi tiếp tục. Không gửi lại số cộng.');
                }
                // If writing this final state fails, the prior "processing" record
                // remains and blocks future grants until investigated manually.
                kp_topup_with_record('grants',$requestId,function($old,$path){
                    if (!$old || $old['status']!=='processing') throw new RuntimeException('Không hoàn tất được nhật ký cấp tiền.');
                    $old['status']='applied';$old['updated_at']=time();kp_topup_write_file($path,$old);
                });
                $notice='Đã cộng '.number_format($units,0,',','.').' '.$names[$currency].' cho '.(string)$char['charname'].'.';
            } finally { @flock($fh,LOCK_UN); fclose($fh); }
        }
    } catch(RuntimeException $e) {$error=$e->getMessage();}
    catch(Throwable $e){kp_log('manual grant page failed',$e);$error='Thao tác chưa xác nhận hoàn tất. Kiểm tra phần đối soát trước khi thực hiện lại.';}
}
try {
    if($search!==''){
        $db=kp_db();
        $st=$db->prepare('SELECT id,username,ban FROM team_user WHERE username=? LIMIT 1');$st->execute([$search]);$owner=$st->fetch();
        if($owner) {
            $st=$db->prepare('SELECT id,charname,lastLv,gold,luong,luonglock,del,ban FROM tob_char WHERE userId=? ORDER BY id DESC LIMIT 40');
            $st->execute([(int)$owner['id']]);$chars=$st->fetchAll();
        }
    }
}catch(Throwable $e){kp_log('manual grant lookup failed',$e);$error='Chưa đọc được danh sách nhân vật.';}
$requestId=kp_topup_new_id();
kp_topup_page_start('Cộng tài nguyên','grant',(string)$admin['username'],true);
?>
<section class="tp-hero"><span class="tp-kicker"><i></i> QUẢN TRỊ / CẤP TÀI NGUYÊN</span><h1>Cộng cho <em>đúng nhân vật.</em></h1><p class="tp-lead">Xu, Lượng thường và Lượng khóa được tách riêng. Đây là thao tác cộng trực tiếp vào nhân vật đã thoát game; có ghi lịch sử đối soát, không tạo bảng SQL.</p></section>
<?php if($error): ?><div class="tp-alert bad" role="alert"><?= kp_e($error) ?></div><?php endif; ?>
<?php if($notice): ?><div class="tp-alert good" role="status"><?= kp_e($notice) ?></div><?php endif; ?>
<section class="tp-section">
<div class="tp-grid two">
<div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">BƯỚC 01</span><h2>Tìm tài khoản game</h2></div></div>
<form method="get" class="tp-form"><label class="tp-field"><span>Tên đăng nhập</span><input class="tp-input" name="username" maxlength="20" value="<?= kp_e($search) ?>" required placeholder="Nhập tên tài khoản game"></label><button class="tp-button" type="submit">Tìm tài khoản ↗</button></form>
<?php if($owner): ?><div class="tp-alert good">Đã tìm thấy: <b><?= kp_e($owner['username']) ?></b> · ID #<?= (int)$owner['id'] ?> · <?= count($chars) ?> nhân vật</div><?php elseif($search!==''): ?><div class="tp-alert warning">Không tìm thấy tài khoản này.</div><?php endif; ?>
</div>
<div class="tp-panel"><div class="tp-panel-head"><div><span class="tp-eyebrow">BƯỚC 02</span><h2>Nhân vật nhận</h2></div></div>
<?php if(!$owner || !$chars): ?><p class="tp-muted">Tìm tài khoản và nhân vật trước khi thao tác.</p>
<?php else: ?><form method="post" class="tp-form" id="manual-grant">
<input type="hidden" name="csrf" value="<?= kp_e($csrf) ?>"><input type="hidden" name="action" value="grant"><input type="hidden" name="username" value="<?= kp_e($owner['username']) ?>"><input type="hidden" name="request_id" value="<?= kp_e($requestId) ?>">
<label class="tp-field"><span>Chọn nhân vật</span><select class="tp-select" name="char_id" required>
<?php foreach($chars as $c): $blocked=(int)$c['del']!==1||(int)$c['ban']!==0; ?>
<option value="<?= (int)$c['id'] ?>" <?= $blocked?'disabled':'' ?>><?= kp_e($c['charname']) ?> · Cấp <?= (int)$c['lastLv'] ?> · Xu <?= number_format((float)$c['gold'],0,',','.') ?> · Lượng <?= number_format((int)$c['luong'],0,',','.') ?> · Khóa <?= number_format((int)$c['luonglock'],0,',','.') ?><?= $blocked?' · KHÔNG KHẢ DỤNG':'' ?></option>
<?php endforeach; ?></select></label>
<div class="tp-row"><label class="tp-field"><span>Loại tài nguyên</span><select class="tp-select" name="currency"><option value="gold">Xu</option><option value="luong">Lượng thường</option><option value="luonglock">Lượng khóa</option></select></label><label class="tp-field"><span>Nhập theo</span><select class="tp-select" name="mode"><option value="direct">Số tài nguyên</option><option value="vnd">Số VNĐ quy đổi</option></select></label></div>
<label class="tp-field"><span>Số lượng hoặc số VNĐ</span><input class="tp-input" type="number" name="quantity" min="1" max="1000000000" step="1" required placeholder="VD: 150"><small>Nhập VNĐ tùy ý, không có gói/mốc nạp bắt buộc. Nếu tỷ lệ loại này bằng 0 thì không quy đổi VNĐ.</small></label>
<label class="tp-field"><span>Lý do cấp tài nguyên</span><textarea class="tp-textarea" name="note" minlength="5" maxlength="200" required placeholder="Ví dụ: Hỗ trợ đơn giao dịch #..."></textarea></label>
<label class="tp-check"><input type="checkbox" name="offline" value="1" required><span>Tôi đã xác nhận nhân vật này <b>đã thoát game</b>, đúng tài khoản và đúng số lượng. Tôi hiểu game server có thể ghi đè số dư nếu nhân vật còn online.</span></label>
<button type="submit" class="tp-button">Xác nhận cộng tài nguyên ↗</button></form><?php endif; ?>
</div>
</div>
<div class="tp-alert warning">Lưu ý: <code>tob_char</code> dùng MyISAM (không có rollback). Nếu thao tác báo chưa rõ kết quả, dừng cộng tiếp cho nhân vật và kiểm tra trang <a class="tp-support" href="/quan-tri/doi-soat">Đối soát</a>. Không gửi cùng một yêu cầu lần hai.</div>
</section>

<script src="/assets/js/topup-forms.js?v=1" defer></script>
<?php kp_topup_page_end(); ?>
