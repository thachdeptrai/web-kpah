<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/rankings.php';

$user=kp_player_account();
$categories=kp_rankings_available();
$type=isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : 'level';
if (!isset($categories[$type])) $type=isset($categories['level'])?'level':(array_key_first($categories)??'');
$current=$type!==''?$categories[$type]:null;
$rows=[];
$error=false;
if($current!==null){try { $rows=kp_rankings_fetch($type); }
catch (Throwable $e) { kp_log('leaderboard unavailable: '.$type,$e); $error=true; }}
kp_player_head('Bảng xếp hạng','rankings',$user);
?>
<section class="portal-hero leaderboard-hero">
    <div class="portal-hero-copy">
        <span class="portal-kicker"><i></i> ĐẠI SẢNH VINH DANH · KPAH</span>
        <h1>Bảng <em>xếp hạng.</em></h1>
        <p>Ai đang dẫn đầu? Khám phá bảng vinh danh về nhân vật, chiến đấu, bang hội, nạp VNĐ và các hoạt động trong game.</p>
    </div>
    <div class="lb-hero-mark" aria-hidden="true">
        <span class="lb-orbit lb-orbit-1"></span><span class="lb-orbit lb-orbit-2"></span>
        <img src="/assets/img/rank-guardian.svg" alt="" width="200" height="225" loading="eager">
        <span class="lb-hero-sparks">✦</span>
    </div>
</section>

<?php
$rankGroups=[
 'Nhân vật & phát triển'=>['level','honor','rich','richluong','strength','playtime','contribution','vip','toppoints'],
 'Chiến đấu & tranh tài'=>['pk','kills','duel','arena','battle','siege','supply'],
 'Bang hội'=>['clan','clanxp','clanwealth'],
 'Nạp & hoạt động'=>['nap','napgame','spending','event','wc','farm']
];
?>
<nav class="rank-group-directory" aria-label="Chọn bảng xếp hạng">
<?php foreach($rankGroups as $groupName=>$keys): if(!array_intersect($keys,array_keys($categories)))continue; ?>
<div class="rank-group"><h2><?=kp_e($groupName)?></h2><div class="rank-categories">
<?php foreach($keys as $key): if(!isset($categories[$key]))continue;$cat=$categories[$key]; ?>
<a class="rank-category<?= $type===$key?' is-selected':'' ?>" href="/bang-xep-hang?type=<?=kp_e($key)?>"<?= $type===$key?' aria-current="page"':'' ?>><span class="rank-category-glint" aria-hidden="true">✧</span><?=kp_e($cat['label'])?></a>
<?php endforeach; ?></div></div>
<?php endforeach; ?></nav>

<section class="rankings-board" aria-labelledby="ranking-heading">
    <?php if($current===null): ?><div class="lb-empty" role="status"><h3>Chưa có bảng xếp hạng nào có dữ liệu</h3><p>Các hạng mục sẽ tự hiển thị khi game ghi nhận thành tích.</p></div><?php else: ?>
    <div class="rankings-board-heading">
        <div>
            <span class="portal-kicker"><i></i> BẢNG VINH DANH</span>
            <h2 id="ranking-heading">Top 10 <em><?= kp_e($current['label']) ?></em></h2>
            <p><?= kp_e($current['description']) ?></p>
        </div>
        <div class="lb-season-stamp"><b>TOP 10</b><small>VINH DANH</small></div>
    </div>
    <?php if($error): ?>
        <div class="lb-empty" role="status"><span aria-hidden="true">◇</span><h3>Chưa tải được bảng xếp hạng</h3><p>Hãy thử lại sau. Các hạng mục khác vẫn có thể hoạt động.</p></div>
    <?php elseif(!$rows): ?>
        <div class="lb-empty" role="status"><span aria-hidden="true">◇</span><h3>Chưa có người trên bảng vinh danh</h3><p>Chưa có thành tích được ghi nhận ở hạng mục này. Hãy quay lại sau nhé.</p></div>
    <?php else: ?>
    <div class="rank-podium rank-podium--count-<?= min(3,count($rows)) ?>" aria-label="Các vị trí dẫn đầu">
        <?php foreach(array_slice($rows,0,3) as $i=>$person):
            $rank=$i+1;
            $name=(string)$person['name'];
            $symbol=function_exists('mb_substr')?mb_strtoupper(mb_substr($name,0,1,'UTF-8'),'UTF-8'):strtoupper(substr($name,0,1));
        ?>
        <article class="podium-card podium-rank-<?= $rank ?>">
            <div class="podium-stage">
                <div class="podium-halo" aria-hidden="true"><span></span><span></span></div>
                <div class="podium-avatar">
                    <img src="<?= kp_e([1=>'assets/img/rank-guardian.svg',2=>'assets/img/rank-sorcerer.svg',3=>'assets/img/rank-archer.svg'][$rank]) ?>" alt="" width="220" height="247" loading="<?= $rank===1?'eager':'lazy' ?>">
                </div>
                <span class="podium-rank-badge">#<?= $rank ?></span>
                <span class="podium-spark podium-spark-a" aria-hidden="true">✦</span>
                <span class="podium-spark podium-spark-b" aria-hidden="true">✧</span>
            </div>
            <div class="podium-info">
                <span class="podium-caption"><?= $rank===1?'QUÁN QUÂN':($rank===2?'Á QUÂN':'HẠNG BA') ?></span>
                <h3 title="<?= kp_e($name) ?>"><?= kp_e($name) ?></h3>
                <div class="podium-value"><span><?= kp_e($current['metric']) ?></span><strong><?= $type==='nap'?number_format((int)$person['score'],0,',','.').'đ':kp_player_format($person['score']) ?></strong></div>
                <?php if(in_array($type,['rich','richluong'],true)): ?>
                <div class="lb-podium-breakdown">
                    <span>Xu <b><?= kp_player_format($type==='rich'?$person['score']:$person['secondary']) ?></b></span>
                    <span>Lượng <b><?= kp_player_format($type==='rich'?$person['secondary']:$person['score']) ?></b></span>
                </div>
                <?php elseif($type==='nap'): ?>
                <div class="lb-podium-breakdown">
                    <span>Ngân hàng <b><?=number_format((int)$person['bank_vnd'],0,',','.')?>đ</b></span>
                    <span>Thẻ cào <b><?=number_format((int)$person['card_vnd'],0,',','.')?>đ</b></span>
                    <?php if((int)$person['stored_vnd']>0):?><span>Tổng nạp SQL <b><?=number_format((int)$person['stored_vnd'],0,',','.')?>đ</b></span><?php endif;?>
                </div>
                <?php endif; ?>
            </div>
            <div class="podium-base"><span><?= sprintf('%02d',$rank) ?></span><i aria-hidden="true">✦</i></div>
        </article>
        <?php endforeach; ?>
    </div>
    <div class="lb-lower">
        <div class="lb-lower-heading">
            <div><span class="portal-kicker"><i></i> DANH SÁCH VINH DANH</span><h3>Hạng 4 – 10</h3></div>
            <span class="lb-count"><?= count($rows) ?> vị trí đã ghi nhận</span>
        </div>
        <?php if(count($rows)<=3): ?>
            <p class="lb-no-more">Chưa có nhân vật tiếp theo trong hạng mục này.</p>
        <?php else: ?>
        <div class="lb-list">
        <?php foreach(array_slice($rows,3,7) as $offset=>$person):
            $rank=$offset+4;
            $name=(string)$person['name'];
            $initial=function_exists('mb_substr')?mb_strtoupper(mb_substr($name,0,1,'UTF-8'),'UTF-8'):strtoupper(substr($name,0,1));
        ?>
            <div class="lb-list-row">
                <span class="lb-list-number"><?= sprintf('%02d',$rank) ?></span>
                <span class="lb-list-avatar" aria-hidden="true"><?= kp_e($initial) ?></span>
                <span class="lb-list-name"><?= kp_e($name) ?></span>
                <?php if($current['secondary']!==null): ?>
                    <span class="lb-list-secondary"><?php if($type==='nap'):?>
                    Ngân hàng <b><?=number_format((int)$person['bank_vnd'],0,',','.')?>đ</b> ·
                    Thẻ cào <b><?=number_format((int)$person['card_vnd'],0,',','.')?>đ</b>
                <?php else:?><?= kp_e($current['secondary']) ?> <b><?= kp_player_format($person['secondary']) ?></b><?php endif;?></span>
                <?php endif; ?>
                <span class="lb-list-score"><small><?= kp_e($current['metric']) ?></small><strong><?= $type==='nap'?number_format((int)$person['score'],0,',','.').'đ':kp_player_format($person['score']) ?></strong></span>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="lb-source">Danh sách cập nhật theo thành tích ghi nhận trên hệ thống. Hình chiến binh chỉ mang tính minh họa.</div>
    <?php endif; ?>
</section>
<div class="portal-return"><a href="/tai-khoan">← Về khu tài khoản</a><a href="/nhan-vat">Nhân vật của tôi ↗</a></div>
<?php kp_player_foot(); ?>
