<?php
/** Shared navigation for all admin sections; no permission is granted by links. */
if(!defined('KP_ROOT')){http_response_code(403);exit;}
function kp_admin_nav($active='home',$role=null){
    $role=$role===null?(string)($_SESSION['kp_admin_role']??''):(string)$role;
    if(!in_array($role,['admin','key'],true))return;
    $all=[
      ['home','/quan-tri','Tổng quan'],
      ['referrals','/quan-tri/ma-gioi-thieu','Mã giới thiệu'],
      ['key-users','/quan-tri/tai-khoan-key','Tài khoản key'],
      ['index','/quan-tri/nap','Quản lý nạp'],
      ['orders','/quan-tri/doi-soat','Đối soát'],
      ['rates','/quan-tri/ty-le','Tỷ lệ & thưởng'],
      ['grant','/quan-tri/cong-tai-nguyen','Cộng tài nguyên'],
      ['points','/quan-tri/diem-tich-luy','Point & quà'],
    ];
    $links=$role==='key'?[$all[1]]:$all;
    ?>
<nav class="kp-admin-nav" aria-label="Điều hướng quản trị">
  <div class="kp-admin-nav-links">
  <?php foreach($links as $item): ?>
    <a href="<?=kp_e($item[1])?>" class="kp-admin-nav-link<?= $active===$item[0]?' is-active':'' ?>"<?= $active===$item[0]?' aria-current="page"':'' ?>><?=kp_e($item[2])?></a>
  <?php endforeach; ?>
  </div>
  <a class="kp-admin-nav-logout" href="/quan-tri/dang-xuat">Đăng xuất ↗</a>
</nav>
    <?php
}
