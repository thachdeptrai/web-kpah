<?php
/**
 * Admin-only manual corrections for team_user.vnd / team_user.tongnap.
 * No payment provider call, no Point award and no SQL migration.
 */
require __DIR__.'/../../includes/bootstrap.php';
require_once __DIR__.'/../../includes/wallet_vnd.php';
require_once __DIR__.'/../_layout.php';
$admin=kp_topup_admin();
$csrf=kp_csrf_token();
$error='';$notice='';$account=null;$history=[];
$username=trim((string)($_POST['username']??$_GET['username']??''));
if(!preg_match('/^[a-zA-Z0-9_.-]{1,32}$/D',$username))$username='';
$fmt=function($n){return number_format((int)$n,0,',','.');};
$recordDir=kp_topup_root().'/manual-wallet';
if(!is_dir($recordDir)&&!@mkdir($recordDir,0750,true)&&!is_dir($recordDir)){
    $error='Không thể mở vùng lưu nhật ký cộng tiền.';
}
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        if($error!=='')throw new RuntimeException($error);
        if(!kp_admin_csrf_valid($_POST['csrf']??null))throw new RuntimeException('Phiên xác nhận đã hết hạn. Tải lại trang.');
        if(($_POST['action']??'')!=='credit')throw new RuntimeException('Thao tác không hợp lệ.');
        list($allowed,$retry)=kp_admin_limit('manual-wallet',(string)$admin['id'],12,3600);
        if(!$allowed)throw new RuntimeException('Đã đạt giới hạn 12 lần/giờ. Vui lòng thử lại sau.');
        if($username==='')throw new RuntimeException('Tên tài khoản không hợp lệ.');
        $token=(string)($_POST['request_id']??'');
        if(!preg_match('/^[a-f0-9]{32}$/D',$token))throw new RuntimeException('Mã yêu cầu không hợp lệ. Tải lại trang.');
        $mode=(string)($_POST['mode']??'');
        if(!in_array($mode,['vnd','tongnap','both'],true))throw new RuntimeException('Chọn loại số dư cần cộng.');
        $amountRaw=trim((string)($_POST['amount']??''));
        if(!preg_match('/^[1-9][0-9]*$/D',$amountRaw)||strlen($amountRaw)>9||(int)$amountRaw>100000000)
            throw new RuntimeException('Số tiền mỗi lần phải từ 1 đến 100.000.000đ.');
        $amount=(int)$amountRaw;
        $note=trim((string)($_POST['note']??''));
        $noteLength=function_exists('mb_strlen')?mb_strlen($note,'UTF-8'):strlen($note);
        if($noteLength<10||$noteLength>200)throw new RuntimeException('Lý do phải dài từ 10 đến 200 ký tự.');
        if(($_POST['ack']??'')!=='1')throw new RuntimeException('Cần xác nhận đã đối chiếu tài khoản và số tiền.');
        $db=kp_db();
        $st=$db->prepare('SELECT id,username,ban FROM team_user WHERE username=? LIMIT 1');
        $st->execute([$username]);$owner=$st->fetch(PDO::FETCH_ASSOC);
        if(!$owner||(int)$owner['ban']!==0)throw new RuntimeException('Tài khoản không tồn tại hoặc bị khóa.');
        $userId=(int)$owner['id'];
        kp_vnd_with_lock($userId,function(PDO $db)use($recordDir,$token,$owner,$userId,$admin,$mode,$amount,$note,&$notice){
            $path=$recordDir.'/'.$token.'.json';
            if(is_file($path))throw new RuntimeException('Mã thao tác đã tồn tại. Không cộng lần hai.');
            // Never allow another manual adjustment while an earlier write outcome is unknown.
            foreach(glob($recordDir.'/*.json')?:[] as $file){
                $r=kp_topup_read_file($file);
                if(is_array($r)&&(int)($r['user_id']??0)===$userId
                   && in_array((string)($r['status']??''),['prepared','needs_review'],true))
                    throw new RuntimeException('Tài khoản có thao tác thủ công chưa đối soát. Dừng cộng thêm.');
            }
            kp_vnd_require_tongnap($db);
            $st=$db->prepare('SELECT vnd,tongnap FROM team_user WHERE id=? AND ban=0 LIMIT 1');
            $st->execute([$userId]);$balance=$st->fetch(PDO::FETCH_ASSOC);
            if(!$balance)throw new RuntimeException('Tài khoản không còn khả dụng.');
            $beforeVnd=kp_vnd_current($db,$userId);
            $rawTotal=(string)($balance['tongnap']??'0');
            if(!preg_match('/^(0|[1-9][0-9]*)(?:\\.0+)?$/D',$rawTotal)||(float)$rawTotal>2000000000)
                throw new RuntimeException('Giá trị tongnap không hợp lệ, cần kiểm tra database.');
            $beforeTotal=(int)$rawTotal;
            $vndAdd=$mode==='tongnap'?0:$amount;
            $totalAdd=$mode==='vnd'?0:$amount;
            if($beforeVnd>2000000000-$vndAdd||$beforeTotal>2000000000-$totalAdd)
                throw new RuntimeException('Số dư sau cộng vượt giới hạn 2 tỷ.');
            $afterVnd=$beforeVnd+$vndAdd;
            $afterTotal=$beforeTotal+$totalAdd;
            $record=[
                'id'=>$token,'user_id'=>$userId,'username'=>(string)$owner['username'],
                'admin_id'=>(int)$admin['id'],'admin_username'=>(string)$admin['username'],
                'mode'=>$mode,'amount'=>$amount,'vnd_added'=>$vndAdd,'tongnap_added'=>$totalAdd,
                'vnd_before'=>$beforeVnd,'vnd_after'=>$afterVnd,
                'tongnap_before'=>$beforeTotal,'tongnap_after'=>$afterTotal,
                'note'=>$note,'status'=>'prepared','created_at'=>time()
            ];
            // Write-ahead audit is mandatory because the game account table can be MyISAM.
            kp_topup_write_file($path,$record);
            try{
                $st=$db->prepare('UPDATE team_user SET vnd=?,tongnap=? WHERE id=? AND COALESCE(vnd,0)=? AND COALESCE(tongnap,0)=? AND ban=0');
                $st->execute([$afterVnd,$afterTotal,$userId,$beforeVnd,$beforeTotal]);
                if($st->rowCount()!==1)throw new RuntimeException('Dữ liệu tài khoản đã thay đổi.');
            }catch(Throwable $e){
                kp_log('manual wallet update outcome uncertain '.$token,$e);
                $record['status']='needs_review';
                try{kp_topup_write_file($path,$record);}catch(Throwable $ignored){}
                throw new RuntimeException('Kết quả cộng chưa rõ. Dừng thao tác và đối chiếu SQL + nhật ký trước khi tiếp tục.');
            }
            // A failed final journal write leaves prepared and blocks further manual credits.
            $record['status']='completed';$record['completed_at']=time();
            try{kp_topup_write_file($path,$record);}
            catch(Throwable $e){
                kp_log('manual wallet final journal failed '.$token,$e);
                throw new RuntimeException('Database có thể đã cộng, nhưng chưa hoàn tất nhật ký. KHÔNG gửi lại. Đối soát mã '.$token.'.');
            }
            $notice='Đã cộng thành công cho '.(string)$owner['username'].' · Mã '.strtoupper(substr($token,0,10)).'.';
        });
    }catch(RuntimeException $e){$error=$e->getMessage();}
    catch(Throwable $e){kp_log('manual wallet admin failed',$e);$error='Không xác nhận được kết quả. Dừng và đối soát nhật ký trước khi thao tác tiếp.';}
}
if($username!==''){
    try{
        $st=kp_db()->prepare('SELECT id,username,ban,vnd,tongnap FROM team_user WHERE username=? LIMIT 1');
        $st->execute([$username]);$account=$st->fetch(PDO::FETCH_ASSOC);
    }catch(Throwable $e){kp_log('manual wallet account lookup failed',$e);$error=$error?:'Không đọc được cột vnd/tongnap. Kiểm tra schema hiện có.';}
}
if(is_dir($recordDir)){
    try{
        foreach(glob($recordDir.'/*.json')?:[] as $path){
            $r=kp_topup_read_file($path);
            if(!is_array($r))continue;
            if($account&&(int)($r['user_id']??0)!==(int)$account['id'])continue;
            $history[]=$r;
        }
        usort($history,function($a,$b){return (int)($b['created_at']??0)<=>(int)($a['created_at']??0);});
        $history=array_slice($history,0,20);
    }catch(Throwable $e){kp_log('manual wallet history failed',$e);$error=$error?:'Nhật ký cộng thủ công có bản ghi lỗi. Kiểm tra trên VPS.';}
}
$requestId=kp_topup_new_id();
kp_topup_page_start('Cộng VNĐ & tổng nạp','wallet-credit',(string)$admin['username'],true);
?>
<section class="tp-hero"><span class="tp-kicker"><i></i> QUẢN TRỊ / ĐIỀU CHỈNH TÀI KHOẢN</span><h1>Cộng <em>VNĐ & tổng nạp.</em></h1><p class="tp-lead">Điều chỉnh thủ công hai cột hiện có trong <code>team_user</code>. Không tạo đơn payOS, không tích Point và không tự ghi nhận thanh toán thực tế.</p></section>
<?php if($error):?><div class="tp-alert bad" role="alert"><?=kp_e($error)?></div><?php endif;?>
<?php if($notice):?><div class="tp-alert good" role="status"><?=kp_e($notice)?></div><?php endif;?>
<section class="tp-section">
<div class="tp-grid two">
<div class="tp-panel">
<div class="tp-panel-head"><div><span class="tp-eyebrow">01 · TRA CỨU</span><h2>Tài khoản nhận</h2></div></div>
<form method="get" class="tp-form">
<label class="tp-field"><span>Tên đăng nhập game</span><input class="tp-input" name="username" value="<?=kp_e($username)?>" maxlength="32" required placeholder="Nhập username"></label>
<button class="tp-button" type="submit">Tìm tài khoản ↗</button>
</form>
<?php if($account):?>
<div class="tp-summary tp-summary--two tp-mt-16">
<div><small>Ví VNĐ hiện tại</small><strong><?=$fmt($account['vnd']??0)?>đ</strong></div>
<div><small>Tổng nạp hiện tại</small><strong><?=$fmt($account['tongnap']??0)?>đ</strong></div>
</div>
<div class="tp-alert <?= (int)$account['ban']===0?'good':'bad' ?>">Tài khoản: <b><?=kp_e($account['username'])?></b> · ID #<?=(int)$account['id']?> · <?=(int)$account['ban']===0?'Đang hoạt động':'Đã khóa — không thể cộng'?></div>
<?php elseif($username!==''):?><div class="tp-alert warning">Không tìm thấy tài khoản.</div><?php endif;?>
</div>
<div class="tp-panel">
<div class="tp-panel-head"><div><span class="tp-eyebrow">02 · XÁC NHẬN</span><h2>Cộng số dư</h2></div></div>
<?php if(!$account||(int)$account['ban']!==0):?><p class="tp-muted">Tra cứu tài khoản đang hoạt động để mở biểu mẫu.</p>
<?php else:?>
<form method="post" class="tp-form" id="manual-wallet-credit">
<input type="hidden" name="csrf" value="<?=kp_e($csrf)?>">
<input type="hidden" name="action" value="credit">
<input type="hidden" name="username" value="<?=kp_e($account['username'])?>">
<input type="hidden" name="request_id" value="<?=kp_e($requestId)?>">
<label class="tp-field"><span>Cộng vào cột nào?</span><select class="tp-select" name="mode" required>
<option value="vnd">Chỉ cộng ví VNĐ</option>
<option value="tongnap">Chỉ cộng tổng nạp</option>
<option value="both">Cộng cả ví VNĐ và tổng nạp</option>
</select><small>Mặc định chỉ cộng ví. Chọn cả hai chỉ khi cần điều chỉnh đồng thời.</small></label>
<label class="tp-field"><span>Số tiền cộng (VNĐ)</span><input class="tp-input" type="number" name="amount" min="1" max="100000000" step="1" required placeholder="Ví dụ: 10000"></label>
<label class="tp-field"><span>Lý do / mã yêu cầu hỗ trợ</span><textarea class="tp-textarea" name="note" minlength="10" maxlength="200" required placeholder="Ví dụ: Điều chỉnh số dư theo biên bản đối soát #..."></textarea></label>
<label class="tp-check"><input type="checkbox" name="ack" value="1" required><span>Tôi đã đối chiếu <b>đúng tài khoản, đúng cột, đúng số tiền</b> và hiểu đây là thao tác cộng thủ công không thể tự hoàn tác.</span></label>
<button class="tp-button" type="submit">Xác nhận cộng ↗</button>
</form>
<?php endif;?>
</div>
</div>
<div class="tp-alert warning">Không dùng chức năng này để xử lý lại đơn payOS/thẻ đang chờ đối soát: có nguy cơ cộng trùng. Nếu nhật ký hiển thị <code>prepared</code> hoặc <code>needs_review</code>, dừng thao tác và kiểm tra dữ liệu trên VPS. Không có SQL migration mới.</div>
</section>
<section class="tp-section"><div class="tp-panel">
<div class="tp-panel-head"><div><span class="tp-eyebrow">NHẬT KÝ THỦ CÔNG</span><h2>20 thao tác gần nhất<?= $account?' · '.kp_e($account['username']):'' ?></h2></div></div>
<?php if(!$history):?><p class="tp-muted">Chưa có thao tác nào được ghi nhận.</p>
<?php else:?><div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Thời gian</th><th>Tài khoản</th><th>Thay đổi</th><th>Trước → sau</th><th>Admin / Lý do</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($history as $h):?><tr>
<td><?=date('d/m/Y H:i',(int)($h['created_at']??0))?><br><small><?=kp_e(strtoupper(substr((string)($h['id']??''),0,10)))?></small></td>
<td><?=kp_e((string)($h['username']??''))?></td>
<td>Ví +<?=$fmt($h['vnd_added']??0)?>đ<br>Tổng +<?=$fmt($h['tongnap_added']??0)?>đ</td>
<td>Ví <?=$fmt($h['vnd_before']??0)?> → <?=$fmt($h['vnd_after']??0)?><br>Tổng <?=$fmt($h['tongnap_before']??0)?> → <?=$fmt($h['tongnap_after']??0)?></td>
<td><?=kp_e((string)($h['admin_username']??''))?><br><small><?=kp_e((string)($h['note']??''))?></small></td>
<td><span class="tp-pill <?=kp_e((string)($h['status']??''))?>"><?=kp_e((string)($h['status']??''))?></span></td>
</tr><?php endforeach;?></tbody></table></div><?php endif;?>
</div></section>
<script src="/assets/js/admin-wallet-credit.js?v=1" defer></script>
<?php kp_topup_page_end(); ?>
