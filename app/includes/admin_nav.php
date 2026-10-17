<?php
/**
 * Unified admin navigation. Desktop: compact grouped menus.
 * Mobile: accessible collapsible drawer. Links never grant permissions.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_admin_nav($active = 'home', $role = null)
{
    $role = $role === null ? (string)($_SESSION['kp_admin_role'] ?? '') : (string)$role;
    if (!in_array($role, ['admin', 'key'], true)) return;
    $pendingRewards = 0;
    if ($role === 'admin') {
        try { $pendingRewards = (int)kp_db()->query("SELECT COUNT(*) FROM kp_points_redemptions WHERE status='pending'")->fetchColumn(); }
        catch (Throwable $e) { /* Point migration can be installed after deployment. */ }
    }

    $items = [
        'home' => ['/quan-tri', 'Tổng quan'],
        'downloads' => ['/quan-tri/tai-game', 'Tải game'],
        'referrals' => ['/quan-tri/ma-gioi-thieu', 'Mã giới thiệu'],
        'giftcodes' => ['/quan-tri/giftcode', 'Giftcode game'],
        'key-users' => ['/quan-tri/tai-khoan-key', 'Tài khoản key'],
        'game-accounts' => ['/quan-tri/tai-khoan-game', 'Tài khoản game'],
        'mod-history' => ['/quan-tri/lich-su-thao-tac', 'Lịch sử thao tác'],
        'index' => ['/quan-tri/nap', 'Quản lý nạp'],
        'orders' => ['/quan-tri/doi-soat', 'Đối soát'],
        'rates' => ['/quan-tri/ty-le', 'Tỷ lệ & thưởng'],
        'wallet-credit' => ['/quan-tri/cong-vnd', 'Cộng VNĐ & tổng nạp'],
        'grant' => ['/quan-tri/cong-tai-nguyen', 'Cộng tài nguyên'],
        'points' => ['/quan-tri/diem-tich-luy', 'Point & quà'],
        'farmer' => ['/quan-tri/diem-nong-dan', 'Điểm nông dân'],
    ];
    $groups = $role === 'key'
        ? ['Tài khoản' => ['referrals']]
        : [
            'Truy cập nhanh' => ['home', 'downloads'],
            'Tài khoản' => ['game-accounts', 'mod-history', 'giftcodes', 'referrals', 'key-users'],
            'Thanh toán' => ['index', 'orders', 'rates', 'wallet-credit'],
            'Vận hành' => ['grant', 'points', 'farmer'],
        ];
    $currentLabel = isset($items[$active]) ? $items[$active][1] : 'Quản trị';
    $desktopGroups = ['Tài khoản', 'Thanh toán', 'Vận hành'];
    $base = $role === 'key' ? '/quan-tri/ma-gioi-thieu' : '/quan-tri';
    ?>
<nav class="kp-admin-nav kp-admin-nav-v2" aria-label="Điều hướng quản trị">
  <a class="kp-admin-nav-brand" href="<?= kp_e($base) ?>" aria-label="KPAH - <?= $role === 'key' ? 'Mã giới thiệu' : 'Tổng quan quản trị' ?>">
    <span class="kp-admin-nav-brand-mark" aria-hidden="true"><img src="/assets/img/emblem.webp" width="40" height="40" alt=""></span>
    <span class="kp-admin-nav-brand-copy"><strong>NGHIỆN KPAH</strong><small><?= $role === 'key' ? 'QUẢN LÝ MÃ · KEY' : 'TRUNG TÂM QUẢN TRỊ' ?></small></span>
  </a>

  <div class="kp-admin-nav-desktop">
    <?php if ($role === 'key'): ?>
      <a class="kp-admin-nav-item<?= $active === 'referrals' ? ' is-active' : '' ?>" href="/quan-tri/ma-gioi-thieu"<?= $active === 'referrals' ? ' aria-current="page"' : '' ?>>Mã giới thiệu</a>
    <?php else: ?>
      <?php foreach ($groups['Truy cập nhanh'] as $id): ?>
        <a class="kp-admin-nav-item<?= $active === $id ? ' is-active' : '' ?>" href="<?= kp_e($items[$id][0]) ?>"<?= $active === $id ? ' aria-current="page"' : '' ?>><?= kp_e($items[$id][1]) ?></a>
      <?php endforeach; ?>
      <?php foreach ($desktopGroups as $heading): ?>
        <?php $selected = in_array($active, $groups[$heading], true); ?>
        <details class="kp-admin-nav-dropdown">
          <summary class="kp-admin-nav-item kp-admin-nav-trigger<?= $selected ? ' is-active' : '' ?>"><?= kp_e($heading) ?><span class="kp-admin-nav-chevron" aria-hidden="true"></span></summary>
          <div class="kp-admin-nav-popover">
            <span class="kp-admin-nav-popover-heading"><?= kp_e($heading) ?></span>
            <?php foreach ($groups[$heading] as $id): ?>
              <a href="<?= kp_e($items[$id][0]) ?>" class="kp-admin-nav-popover-link<?= $active === $id ? ' is-active' : '' ?>"<?= $active === $id ? ' aria-current="page"' : '' ?>><?= kp_e($items[$id][1]) ?><?php if($id==='points'&&$pendingRewards>0):?><b class="kp-admin-nav-badge"><?=$pendingRewards>99?'99+':$pendingRewards?></b><?php endif;?><span aria-hidden="true">↗</span></a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="kp-admin-nav-actions">
    <a class="kp-admin-nav-exit" href="/quan-tri/dang-xuat" title="Đăng xuất khỏi quản trị">Đăng xuất <span aria-hidden="true">↗</span></a>
    <details class="kp-admin-nav-mobile">
      <summary class="kp-admin-nav-mobile-toggle">
        <span class="kp-admin-nav-hamburger" aria-hidden="true"><i></i><i></i><i></i></span>
        <span class="kp-admin-nav-mobile-title">Menu <small><?= kp_e($currentLabel) ?></small></span>
        <span class="kp-admin-nav-chevron" aria-hidden="true"></span>
      </summary>
      <div class="kp-admin-nav-mobile-panel">
        <div class="kp-admin-nav-mobile-heading"><strong>Điều hướng quản trị</strong><span><?= kp_e($currentLabel) ?></span></div>
        <?php foreach ($groups as $heading => $ids): ?>
          <div class="kp-admin-nav-mobile-group">
            <span class="kp-admin-nav-mobile-group-title"><?= kp_e($heading) ?></span>
            <?php foreach ($ids as $id): ?>
              <a class="kp-admin-nav-mobile-link<?= $active === $id ? ' is-active' : '' ?>" href="<?= kp_e($items[$id][0]) ?>"<?= $active === $id ? ' aria-current="page"' : '' ?>><span><?= kp_e($items[$id][1]) ?><?php if($id==='points'&&$pendingRewards>0):?> <b class="kp-admin-nav-badge"><?=$pendingRewards>99?'99+':$pendingRewards?></b><?php endif;?></span><span aria-hidden="true">↗</span></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <div class="kp-admin-nav-mobile-bottom">
          <a href="/" target="_blank" rel="noopener noreferrer">Xem website <span aria-hidden="true">↗</span></a>
          <a href="/quan-tri/dang-xuat">Đăng xuất <span aria-hidden="true">↗</span></a>
        </div>
      </div>
    </details>
  </div>
</nav>
<script src="/assets/js/admin-nav.js?v=1" defer></script>
<script src="/assets/js/ui-system.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/ui-system.js') ?>" defer></script>
<script src="/assets/js/fantasy-foundation.js?v=<?= (int)@filemtime(KP_ROOT.'/assets/js/fantasy-foundation.js') ?>" defer></script>
    <?php
}
