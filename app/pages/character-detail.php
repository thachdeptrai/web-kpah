<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/player_portal.php';
require_once __DIR__.'/../includes/character_inventory.php';
$user = kp_player_account();
$name = trim((string)($_GET['name'] ?? ''));
$character = null;
$groups = [];
$templates = [];
$attributeLabels = [];
$error = false;
try {
    $pdo = kp_db();
    $character = kp_inventory_character($pdo, (int)$user['id'], $name);
    if ($character) {
        foreach (['equip'=>'Trang bị', 'inven'=>'Hành trang', 'bag'=>'Rương đồ', 'tuido'=>'Túi đồ'] as $column=>$label) {
            $groups[$column] = [
                'label' => $label,
                'items' => kp_inventory_parse($character[$column] ?? ''),
            ];
        }
        $templates = kp_inventory_templates($pdo, array_map(function($g){ return $g['items']; },$groups));
        try { $attributeLabels = kp_inventory_attribute_labels($pdo); }
        catch (Throwable $e) { kp_log('inventory attribute names unavailable', $e); }
    }
} catch (Throwable $e) { kp_log('character inventory unavailable', $e); $error=true; }
if (!$error && !$character) http_response_code(404);
kp_player_head('Hồ sơ nhân vật','characters',$user,true);
?>
<section class="portal-hero portal-hero--compact"><div class="portal-hero-copy">
<span class="portal-kicker">✦ HỒ SƠ NHÂN VẬT NÂNG CAO</span>
<h1><?= $character ? kp_e((string)$character['charname']) : 'Không tìm thấy nhân vật' ?></h1>
<p>Trang xem đồ chỉ đọc. Thông tin được lấy từ dữ liệu nhân vật đã lưu trong game.</p>
</div><a class="portal-hero-action" href="/nhan-vat">← Danh sách nhân vật</a></section>
<?php if ($error): ?>
<div class="ci-empty">Chưa tải được thông tin nhân vật. Hãy thử lại sau.</div>
<?php elseif (!$character): ?>
<div class="ci-empty">Nhân vật không tồn tại hoặc không thuộc tài khoản đang đăng nhập.</div>
<?php else: ?>
<section class="ci-summary" aria-label="Chỉ số nhân vật">
<?php foreach (['lastLv'=>'Cấp độ','xp'=>'Kinh nghiệm','gold'=>'Xu','luong'=>'Lượng','luonglock'=>'Lượng khóa','hp'=>'HP','mp'=>'MP'] as $field=>$label): ?>
<?php if (in_array($field, ['hp','mp'], true) && (float)($character[$field] ?? 0) <= 0) continue; ?>
<div><span><?= kp_e($label) ?></span><strong><?= kp_e(kp_player_format($character[$field] ?? 0)) ?></strong></div>
<?php endforeach; ?>
</section>
<nav class="ci-tabs" aria-label="Danh mục đồ">
<?php foreach ($groups as $key=>$group): ?><a href="#ci-<?= kp_e($key) ?>"><?= kp_e($group['label']) ?> <span><?= count($group['items']) ?></span></a><?php endforeach; ?>
</nav>
<?php foreach ($groups as $key=>$group): ?>
<section id="ci-<?= kp_e($key) ?>" class="ci-section">
<div class="ci-heading"><h2><?= kp_e($group['label']) ?></h2><span><?= count($group['items']) ?> vật phẩm</span></div>
<?php if (!$group['items']): ?><p class="ci-empty">Không có vật phẩm trong mục này.</p><?php else: ?>
<div class="ci-grid">
<?php foreach ($group['items'] as $index=>$item):
    $template=$templates[$item['template_id']] ?? null;
    $icon=kp_inventory_icon_url($template);
    $itemName=$template ? (string)$template['name'] : 'Vật phẩm #'.$item['template_id'];
?>
<article class="ci-item">
<div class="ci-icon">
<?php if ($icon !== ''): ?><img src="<?= kp_e($icon) ?>" alt="" width="60" height="60" loading="lazy"><?php endif; ?>
<span aria-hidden="true">✦</span>
</div>
<div class="ci-item-copy"><strong><?= kp_e($itemName) ?></strong>
<small>Vị trí <?= $index+1 ?><?= $item['plus']>0 ? ' · +'.(int)$item['plus'] : '' ?></small>
<div class="ci-chips">
<?php if ($item['level']>0): ?><span>Cấp <?= (int)$item['level'] ?></span><?php endif; ?>
<?php if ($item['durability']!==null && $item['max_durability']!==null): ?><span>Độ bền <?= (int)$item['durability'] ?>/<?= (int)$item['max_durability'] ?></span><?php endif; ?>

</div>
<?php if ($item['attributes']): ?>
<details class="ci-attrs"><summary>Chi tiết thuộc tính</summary><div class="ci-chips">
<?php foreach ($item['attributes'] as $attribute=>$value): ?><span><?= kp_e(kp_inventory_attribute_name($attribute, $attributeLabels)) ?>: <?= (int)$value ?></span><?php endforeach; ?>
</div></details>
<?php endif; ?>
</div></article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
<div class="portal-return"><a href="/nhan-vat">← Quay lại nhân vật</a></div>
<script src="/assets/js/character-inventory.js?v=1" defer></script>
<?php kp_player_foot(); ?>
